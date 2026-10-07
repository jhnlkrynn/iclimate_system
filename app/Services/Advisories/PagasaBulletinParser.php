<?php

namespace App\Services\Advisories;

use App\Support\LianBarangays;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use RuntimeException;

/** Parse only bulletin content, never navigation or another province's default forecast. */
class PagasaBulletinParser
{
    /**
     * Batangas cities and municipalities other than Lian. A bulletin that names only these (e.g. "Nasugbu and
     * Calatagan, Batangas") does not cover Lian, even though it mentions Batangas.
     */
    private const OTHER_BATANGAS_TOWNS = [
        'Agoncillo', 'Alitagtag', 'Balayan', 'Balete', 'Batangas City', 'Bauan', 'Calaca', 'Calatagan', 'Cuenca',
        'Ibaan', 'Laurel', 'Lemery', 'Lipa', 'Lobo', 'Mabini', 'Malvar', 'Mataasnakahoy', 'Mataas na Kahoy',
        'Nasugbu', 'Padre Garcia', 'Rosario', 'San Jose', 'San Juan', 'San Luis', 'San Nicolas', 'San Pascual',
        'Santa Teresita', 'Sta. Teresita', 'Santo Tomas', 'Sto. Tomas', 'Taal', 'Talisay', 'Tanauan', 'Taysan',
        'Tingloy', 'Tuy',
    ];

    /**
     * True when every mention of Batangas in the text is limited to other Batangas towns ("Nasugbu, Batangas",
     * "Lipa City and Tanauan in Batangas", "Batangas City") and Lian is not named. A province-wide mention
     * ("Batangas", "Batangas Province") keeps the bulletin relevant to Lian.
     */
    public static function namesOnlyOtherBatangasTowns(string $text): bool
    {
        if (preg_match('/\bLian\b/i', $text) || ! preg_match_all('/\bBatangas\b/i', $text, $mentions, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $town = '(?:'.implode('|', array_map(fn ($t) => preg_quote($t, '/'), self::OTHER_BATANGAS_TOWNS)).')(?:\s+City)?';
        $listBefore = '/(?<![\pL\d])'.$town.'(?:\s*(?:,|\band\b|&)\s*'.$town.')*\s*(?:,|\bin\b|\bof\b)\s*$/iu';

        foreach ($mentions[0] as [$word, $offset]) {
            $before = substr($text, max(0, $offset - 200), min(200, $offset));
            $after = substr($text, $offset + strlen($word), 40);
            $limited = preg_match('/^\s+City\b/i', $after)                // Batangas City is its own city
                || preg_match('/^\s*\(/', $after)                         // "Batangas (towns)" is handled by location()
                || preg_match($listBefore, $before);                       // "Nasugbu and Calatagan, Batangas"
            if (! $limited) {
                return false;
            }
        }

        return true;
    }

    public function parse(string $html, array $source): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $bulletins = [];
        $recognized = false;

        // PAGASA puts the selected province's data in this JSON assignment.
        // Decode data only: never execute remote JavaScript.
        if (preg_match('/\blet\s+regional\s*=\s*(\{[^\r\n]+\})\s*;?/', $html, $match)) {
            $regional = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($regional['provincial']['data'] ?? null)) {
                throw new RuntimeException('PAGASA provincial forecast data structure is missing.');
            }
            $recognized = true;
            foreach ($regional['provincial']['data'] ?? [] as $province) {
                if (strcasecmp($province['name'] ?? '', 'Batangas') !== 0) {
                    continue;
                }
                $outlook = $province['outlook'][0] ?? [];
                $text = implode(' ', array_unique(array_filter([
                    $outlook['day_description'] ?? null, $outlook['night_description'] ?? null,
                ])));
                if ($text === '' || empty($regional['weather']['issued_date']) || empty($regional['weather']['valid_date'])) {
                    throw new RuntimeException('PAGASA Batangas forecast text or validity dates are missing.');
                }
                if ($text !== '') {
                    $bulletins[] = $this->bulletin('Batangas Provincial Weather Forecast', $text,
                        ['scope_type' => 'province', 'scope_name' => 'Lian, Batangas · Province-wide advisory', 'barangays' => []],
                        $this->date($regional['weather']['issued_date'] ?? null),
                        $this->date($regional['weather']['valid_date'] ?? null), 'provincial_forecast');
                }
            }
        }

        // Each warning is its own div. A province selector elsewhere on the
        // page is not evidence that a warning applies to that province.
        foreach ($xpath->query('//*[@id="rainfalls" or @id="thunderstorms"]/div') as $node) {
            $recognized = true;
            $text = $this->text($document->saveHTML($node));
            if (preg_match('/no (?:heavy rainfall|thunderstorm).*?(?:issued|warning|advisory)/i', $text)) {
                continue;
            }
            $bulletins[] = $this->fromText($text);
        }

        $article = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " article-content ")]')->item(0);
        if ($article && ! str_contains($html, 'let regional')) {
            $recognized = true;
            $articleText = $this->text($document->saveHTML($article));
            if (str_contains($source['url'], 'ten-day-regional-agri-weather')) {
                if (! preg_match('/(\d{1,2})\s*[-–]\s*(\d{1,2})\s+([A-Z]+)\s+(20\d{2})/i', $articleText, $period)) {
                    throw new RuntimeException('PAGASA agricultural forecast validity period is missing.');
                }
                foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " region-list ")]') as $region) {
                    $text = $this->text($document->saveHTML($region));
                    if (! preg_match('/CALABARZON|Region\s*(?:IV[- ]?A|4\s*A)/i', $text)) {
                        continue;
                    }
                    $bulletins[] = $this->bulletin('CALABARZON Agricultural Weather Outlook', $text,
                        ['scope_type' => 'regional', 'scope_name' => 'CALABARZON (includes Lian, Batangas)', 'barangays' => []],
                        $this->labelledDate($articleText, 'Issued (?:at|on)'),
                        $this->date("{$period[2]} {$period[3]} {$period[4]}")?->timezone('Asia/Manila')->endOfDay()->utc(), 'agri_outlook',
                        $this->date("{$period[1]} {$period[3]} {$period[4]}"),
                        $this->agriOutlook($xpath, $region));
                }
            } else {
                $published = $this->labelledDate($articleText, 'Issued (?:at|on)');
                $until = $this->labelledDate($articleText, 'Valid until');
                if (! $published || ! $until || ! $xpath->query('.//table//tr', $article)->length) {
                    throw new RuntimeException('PAGASA weekly outlook publication, validity or table structure is missing.');
                }
                foreach ($xpath->query('.//table//tr', $article) as $row) {
                    $text = $this->text($document->saveHTML($row));
                    $rowUntil = $until;
                    if (preg_match('/^([A-Za-z]+)\s+(\d{1,2})\s*[-–]\s*(\d{1,2})/', $text, $window) && $published) {
                        $year = $published->copy()->timezone('Asia/Manila')->year;
                        $start = $this->date("{$window[2]} {$window[1]} {$year}");
                        $end = $this->date("{$window[3]} {$window[1]} {$year}")->timezone('Asia/Manila')->endOfDay()->utc();
                        if (now()->lt($start) || now()->gt($end)) {
                            continue;
                        }
                        $rowUntil = $until ? $until->min($end) : $end;
                    }
                    $scope = $this->location($text);
                    if (! $scope && preg_match('/the rest of the country (?:will|may)/i', $text, $rest, PREG_OFFSET_CAPTURE)) {
                        // Use only the nationwide sentence, not severe weather
                        // described earlier for unrelated provinces.
                        $text = substr($text, $rest[0][1]);
                        $scope = ['scope_type' => 'regional', 'scope_name' => 'National outlook (includes Lian, Batangas)', 'barangays' => []];
                    }
                    if ($scope) {
                        $bulletins[] = $this->bulletin('PAGASA Weekly Weather Outlook', $text, $scope, $published, $rowUntil, 'weekly_outlook');
                    }
                }
            }
        }

        // Plain-text bulletins are also supported (including deterministic
        // test fixtures). Do not fall back to flattening a full portal page.
        if (! $recognized && ! $xpath->query('//nav | //select | //header')->length) {
            $text = $this->text($html);
            if (preg_match('/(?:weather|rainfall|thunderstorm|cyclone|forecast|outlook|advisory)/i', $text)) {
                $recognized = true;
                $bulletins[] = $this->fromText($text);
            }
        }
        if (! $recognized) {
            throw new RuntimeException('PAGASA page structure is unrecognized; no bulletin content could be parsed.');
        }

        return array_values(array_filter($bulletins));
    }

    private function fromText(string $text): ?array
    {
        $scope = $this->location($text);
        $family = match (true) {
            (bool) preg_match('/thunderstorm/i', $text) => 'thunderstorm',
            (bool) preg_match('/rainfall (?:warning|advisory)/i', $text) => 'rainfall',
            (bool) preg_match('/tropical cyclone|typhoon/i', $text) => 'cyclone',
            default => 'weather',
        };
        $published = $this->labelledDate($text, 'Issued (?:at|on)');
        $until = $this->labelledDate($text, 'Valid until');
        if (! $until && $published) {
            preg_match('/(?:next|within|persist within)\s+(\d+)\s*hours?/i', $text, $duration);
            $until = $published->copy()->addHours(isset($duration[1]) ? min(24, (int) $duration[1]) : 6);
        }
        $title = preg_match('/^(.+?(?:Advisory|Warning|Bulletin)(?:\s+No\.?\s*\d+)?)/i', $text, $match)
            ? trim($match[1]) : 'PAGASA Weather Advisory';

        return $this->bulletin($title, $text, $scope, $published, $until, $family);
    }

    /** Municipality lists in a province warning restrict its actual coverage. */
    public function location(string $text): ?array
    {
        if (preg_match('/(?:except|excluding)\s+(?:Lian|Batangas)/i', $text)) {
            return null;
        }
        if (preg_match('/\bBatangas\s*\(([^)]+)\)/i', $text, $towns) && ! preg_match('/\bLian\b/i', $towns[1])) {
            return null;
        }
        // Common barangay names (San Diego, etc.) in another province are not Lian barangays.
        if (! preg_match('/\b(?:Lian|Batangas|CALABARZON)\b|Region\s*(?:IV[- ]?A|4\s*A)/i', $text)
            && preg_match('/\b(?:Quezon|Cavite|Laguna|Rizal|Cebu|Bulacan|Pampanga|Mindoro|Davao|Iloilo)\b/i', $text)) {
            return null;
        }
        // A warning for other Batangas towns only ("Nasugbu, Batangas") does not reach Lian.
        if (self::namesOnlyOtherBatangasTowns($text)) {
            return null;
        }
        // Barangay names are shared with other towns (Balibago, San Diego, Cumba...), so a barangay counts as
        // Lian's only when the bulletin names Lian; PAGASA is never credited with a barangay it did not name.
        $barangays = [];
        foreach (preg_match('/\bLian\b/i', $text) ? LianBarangays::all() : [] as $barangay) {
            $aliases = [$barangay, str_replace('-', ' ', $barangay)];
            if (preg_match('/Barangay\s+(\d+)/', $barangay, $number)) {
                $aliases[] = 'Brgy. '.$number[1];
                $aliases[] = 'Barangay '.$number[1];
            }
            foreach ($aliases as $alias) {
                if (preg_match('/(?<![\pL\d])'.preg_quote($alias, '/').'(?![\pL\d])/iu', $text)) {
                    $barangays[] = $barangay;
                    break;
                }
            }
        }
        if ($barangays) {
            return ['scope_type' => 'barangay', 'scope_name' => implode(', ', $barangays).', Lian', 'barangays' => $barangays];
        }

        return match (true) {
            (bool) preg_match('/\bLian\b/i', $text) => ['scope_type' => 'municipality', 'scope_name' => 'All Lian Barangays', 'barangays' => []],
            (bool) preg_match('/\bCALABARZON\b|Region\s*(?:IV[- ]?A|4\s*A)\b/i', $text)
                && ! preg_match('/(?:except|excluding)\s+Batangas/i', $text) => ['scope_type' => 'regional', 'scope_name' => 'CALABARZON (includes Lian, Batangas)', 'barangays' => []],
            (bool) preg_match('/\bBatangas\b/i', $text) => ['scope_type' => 'province', 'scope_name' => 'Lian, Batangas · Province-wide advisory', 'barangays' => []],
            default => null,
        };
    }

    /** @param  array<string, mixed>|null  $details  Structured content read from the page (e.g. the agri outlook table). */
    private function bulletin(string $title, string $text, ?array $scope, ?Carbon $published, ?Carbon $until, string $family, ?Carbon $from = null, ?array $details = null): array
    {
        return compact('title', 'text', 'scope', 'published', 'until', 'family', 'from', 'details');
    }

    /**
     * The ten-day agri outlook table for one region, read from its own cells: the four weather values exactly as
     * PAGASA wrote them, and each crop's stages and the farm operations as separate items (the page lists them in
     * <li> elements). Nothing is reworded; a part the page does not have is left out.
     *
     * @return array{weather: array<string, string>, crops: array<string, list<string>>, farm_operations: list<string>}|null
     */
    private function agriOutlook(DOMXPath $xpath, \DOMNode $region): ?array
    {
        $weather = [];
        foreach ($xpath->query('.//thead//th', $region) as $cell) {
            $value = $xpath->query('.//p', $cell)->item(0);
            if (! $value) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', str_replace($value->textContent, '', $cell->textContent)));
            $key = match (true) {
                (bool) preg_match('/rainfall/i', $label) => 'rainfall_mm',
                (bool) preg_match('/humidity/i', $label) => 'relative_humidity_percent',
                (bool) preg_match('/rainy days/i', $label) => 'rainy_days',
                (bool) preg_match('/temperature/i', $label) => 'temperature_c',
                default => null,
            };
            if ($key) {
                $weather[$key] = trim(preg_replace('/\s+/u', ' ', $value->textContent));
            }
        }

        $crops = [];
        $operations = [];
        foreach ($xpath->query('.//tbody//tr', $region) as $row) {
            $items = [];
            foreach ($xpath->query('.//li', $row) as $item) {
                $items[] = trim(preg_replace('/\s+/u', ' ', $item->textContent));
            }
            $items = array_values(array_filter($items));
            $heads = [];
            foreach ($xpath->query('./th', $row) as $head) {
                if (! $xpath->query('.//li|.//img', $head)->length) {
                    $heads[] = trim(preg_replace('/\s+/u', ' ', $head->textContent));
                }
            }
            $name = $heads[0] ?? '';
            if ($items === [] || $name === '') {
                continue;
            }
            if (preg_match('/farm operations/i', $name)) {
                $operations = $items;
            } else {
                $crops[ucwords(strtolower($name))] = $items;
            }
        }

        return $weather || $crops || $operations ? ['weather' => $weather, 'crops' => $crops, 'farm_operations' => $operations] : null;
    }

    private function labelledDate(string $text, string $label): ?Carbon
    {
        if (preg_match('/'.$label.'\s*:\s*((?:\d{1,2}:\d{2}\s*[AP]M,?\s*)?\d{1,2}\s+[A-Za-z]+,?\s+20\d{2})/i', $text, $match)) {
            return $this->date($match[1]);
        }

        return null;
    }

    private function date(?string $text): ?Carbon
    {
        if ($text && preg_match('/^(\d{1,2}:\d{2}\s*[AP]M),?\s*(.+)$/i', $text, $parts)) {
            $text = $parts[2].' '.$parts[1];
        }

        return $text ? Carbon::parse(str_replace(',', '', $text), 'Asia/Manila')->utc() : null;
    }

    private function text(string $html): string
    {
        $html = preg_replace('/<!--.*?-->|<(script|style|nav|header|footer|select)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $html = preg_replace('/<[^>]+>/', ' ', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
