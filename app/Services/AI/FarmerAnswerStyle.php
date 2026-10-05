<?php

namespace App\Services\AI;

/**
 * Short Climora answers for farmers: Answer → Reason → Action, in 2–5 plain sentences.
 *
 * Only the wording changes. Every answer is built from the decision-support result the full report
 * already uses (planting items, irrigation recommendation, yield, warnings); nothing is recalculated.
 * The full report stays available: the caller stores it as the chat's details, shown when the farmer
 * asks "Why?", "Show me the details" or "How did you calculate that?".
 */
class FarmerAnswerStyle
{
    private const DETAILS = '/\b(why|details?|explain|explanation|how did you|how do you know|calculat\w*|computation|show me the (data|numbers|figures)|bakit|paano mo|detalye|ipaliwanag)\b/iu';

    /** The farmer asked for the reasoning or the numbers, so the full report is the right answer. */
    public static function asksForDetails(string $question): bool
    {
        return preg_match(self::DETAILS, $question) === 1;
    }

    /** A short follow-up such as "Why?" or "Show me the details" that refers to the previous answer. */
    public static function isDetailsFollowUp(string $question): bool
    {
        return self::asksForDetails($question) && str_word_count(preg_replace('/[^\pL\s]/u', ' ', $question) ?? '') <= 6;
    }

    /** The line that tells the farmer the details are one question away. */
    public static function detailsHint(bool $tagalog): string
    {
        return $tagalog ? 'Itanong ang "Bakit?" para sa detalye.' : 'Ask "Why?" for the details.';
    }

    /**
     * @param  array<string, mixed>  $decision  DecisionSupportService::evaluate() result
     */
    public static function brief(string $intent, array $decision, array $weather, array $yield, array $warnings, array $insights, string $farmType, bool $tagalog): ?string
    {
        $lines = match ($intent) {
            IntentDetectionService::PLANTING_RECOMMENDATION => self::planting($decision, $farmType, $tagalog),
            IntentDetectionService::IRRIGATION_RECOMMENDATION => self::irrigation((string) data_get($decision, 'irrigation.recommendation'), $tagalog),
            IntentDetectionService::RICE_YIELD_PREDICTION => self::riceYield($yield, $tagalog),
            IntentDetectionService::WEATHER_PREDICTION => self::weather($weather, $tagalog),
            IntentDetectionService::CLIMATE_RISK => self::climateRisk($warnings, $tagalog),
            IntentDetectionService::FARMING_ADVISORY => self::farmingAdvice($insights, $tagalog),
            default => null,
        };

        return $lines === null ? null : self::format($lines, $tagalog);
    }

    /** Monthly weather estimate for the farmer: rainfall, what it means, and what to do. No model name. */
    public static function monthlyWeather(string $month, mixed $rainfall, mixed $temperature, mixed $humidity, bool $tagalog): string
    {
        $month = self::month($month, $tagalog);
        $temp = is_numeric($temperature) ? self::number($temperature).'°C' : null;
        $answer = match (true) {
            is_numeric($rainfall) && $temp !== null => $tagalog
                ? "Sa {$month}, inaasahan ang ".self::number($rainfall)." mm na ulan at {$temp} na temperatura."
                : "In {$month}, expect ".self::number($rainfall)." mm of rain and a temperature of {$temp}.",
            is_numeric($rainfall) => $tagalog ? "Sa {$month}, inaasahan ang ".self::number($rainfall).' mm na ulan.' : "In {$month}, expect ".self::number($rainfall).' mm of rain.',
            default => $tagalog ? "Walang sapat na forecast ng ulan para sa {$month}." : "There is not enough rainfall forecast for {$month}.",
        };
        [$reason, $action] = self::rainMeaning(is_numeric($rainfall) ? (float) $rainfall : null, $tagalog);

        return self::format([$answer, $reason, $action], $tagalog);
    }

    /**
     * A source figure as the source gave it (29.4 stays 29.4, 3.2 stays 3.2): only float noise past two decimals and
     * trailing zeros are dropped. Simpler answers come from the wording, never from rounding the data.
     */
    public static function number(mixed $value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /**
     * A weather condition (WeatherConditionLabel / the providers' fixed labels) in the farmer's language.
     * $form "noun" fits "Current weather in Lian: light rain"; "sentence" follows a day: "Tomorrow may have light rain."
     */
    public static function condition(?string $condition, bool $tagalog, string $form = 'noun'): string
    {
        $key = strtolower(trim((string) $condition));
        // [English noun, Tagalog noun, English after a day, Tagalog after "<day> ay"]
        $known = [
            'thunderstorm' => ['thunderstorms', 'may kulog at kidlat', 'may have thunderstorms', 'may kulog at kidlat'],
            'heavy rain' => ['heavy rain', 'malakas na ulan', 'may have heavy rain', 'may malakas na ulan'],
            'moderate rain' => ['moderate rain', 'katamtamang ulan', 'may have moderate rain', 'may katamtamang ulan'],
            'light rain' => ['light rain', 'mahinang ulan', 'may have light rain', 'may mahinang ulan'],
            'rain showers' => ['rain showers', 'pabugso-bugsong ulan', 'may have rain showers', 'may pabugso-bugsong ulan'],
            'rain' => ['rain', 'ulan', 'may have rain', 'may ulan'],
            'foggy' => ['foggy', 'mahamog', 'may be foggy', 'mahamog'],
            'overcast' => ['overcast', 'makulimlim', 'will be overcast', 'makulimlim'],
            'partly cloudy' => ['partly cloudy', 'bahagyang maulap', 'will be partly cloudy', 'bahagyang maulap'],
            'cloudy' => ['cloudy', 'maulap', 'will be cloudy', 'maulap'],
            'clear' => ['clear', 'maaliwalas', 'will be clear', 'maaliwalas'],
            'clear night' => ['clear', 'maaliwalas', 'will be clear', 'maaliwalas'],
            'sunny' => ['sunny', 'maaraw', 'will be sunny', 'maaraw'],
        ];
        $match = $known[$key] ?? collect($known)->first(fn ($words, $label) => str_contains($key, $label));

        if ($match === null) {
            // Not a label we know: say it plainly rather than guess a translation.
            return match (true) {
                $key === '' => $tagalog ? 'hindi tiyak ang panahon' : ($form === 'sentence' ? 'has an uncertain forecast' : 'uncertain'),
                $tagalog => 'may kondisyong "'.trim((string) $condition).'"',
                default => $form === 'sentence' ? 'will be '.$key : $key,
            };
        }

        return $match[($tagalog ? 1 : 0) + ($form === 'sentence' ? 2 : 0)];
    }

    /** A forecast day ("Today", "Tomorrow", "Mon", or a date) as the farmer says it: Bukas, Lunes, Tomorrow, Monday. */
    public static function day(array $day, bool $tagalog): string
    {
        $en = ['Today', 'Tomorrow', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $tl = ['Ngayong araw', 'Bukas', 'Lunes', 'Martes', 'Miyerkules', 'Huwebes', 'Biyernes', 'Sabado', 'Linggo'];
        $label = null;

        try {
            if (filled($day['date'] ?? null)) {
                $date = \Carbon\CarbonImmutable::parse((string) $day['date'])->startOfDay();
                $ahead = (int) \App\Support\BusinessClock::today()->diffInDays($date, false);
                $label = $ahead === 0 ? 'Today' : ($ahead === 1 ? 'Tomorrow' : $date->format('l'));
            }
        } catch (\Throwable) {
            $label = null;
        }

        if ($label === null) {
            $given = trim((string) ($day['day'] ?? ''));
            $label = collect($en)->first(fn ($name) => strcasecmp($given, $name) === 0 || (strlen($given) === 3 && stripos($name, $given) === 0));
            if ($label === null) {
                return $given !== '' ? $given : ($tagalog ? 'Sa susunod na araw' : 'The next day');
            }
        }

        return $tagalog ? $tl[array_search($label, $en, true)] : $label;
    }

    /** "October 2026" → "Oktubre 2026" in Tagalog. */
    private static function month(string $month, bool $tagalog): string
    {
        if (! $tagalog) {
            return $month;
        }

        return str_ireplace(
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'],
            $month
        );
    }

    /** @return list<string> */
    private static function planting(array $decision, string $farmType, bool $tagalog): array
    {
        $items = (array) data_get($decision, 'planting.items', []);
        $has = fn (string $start): bool => collect($items)->contains(fn ($item) => str_starts_with((string) $item, $start));
        $rainfed = strtolower($farmType) !== 'irrigated';
        $lowYield = $has('Consider adjusting the planting schedule');

        if ($has('Delay planting due to flood risk')) {
            return $tagalog
                ? ['Maghintay muna bago magtanim.', 'Puwedeng sumobra ang tubig sa bukid dahil sa malakas na ulan.', 'Magtanim kapag mas ligtas na ang kondisyon.']
                : ['Wait before planting.', 'Heavy rain may cause too much water in the field.', 'Plant when conditions become safer.'];
        }

        if ($has('Wait for sufficient rainfall') || $has('Delay planting during dry season')) {
            if ($rainfed) {
                return $tagalog
                    ? ['Magtanim lang kung sapat ang tubig na available.', 'Mababa ang ulan at umaasa sa ulan ang bukid mo. Siguraduhing may patubig bago magtanim.']
                    : ['Plant only if you have enough water available.', 'Rainfall is low, so make sure irrigation is available before planting.'];
            }

            return $tagalog
                ? ['Magtanim lang kung sapat ang tubig na available.', 'Mababa ang ulan, kaya siguraduhing may patubig.']
                : ['Plant only if you have enough water available.', 'Rainfall is low, so make sure irrigation is available.'];
        }

        if ($has('Plant with caution')) {
            return $tagalog
                ? ['Puwede kang magtanim, pero bantayan nang mabuti ang panahon.', 'Maaaring mas marami ang ulan kaysa sa kailangan.', 'Tingnan ang ulan at linisin ang drainage bago magtanim.']
                : ['You can plant, but monitor the weather closely.', 'There may be more rain than the rice needs.', 'Check rainfall and keep drainage canals clear before planting.'];
        }

        if ($has('Ideal planting period')) {
            return array_filter($tagalog
                ? ['Puwede ka nang magtanim.', 'Angkop ang panahon para sa pagtatanim ng palay.', $lowYield ? 'Mababa ang inaasahang ani, kaya isipin ang pag-ayos ng iskedyul ng pagtatanim.' : 'Patuloy na bantayan ang panahon at siguraduhing may sapat na tubig ang bukid.']
                : ['You can plant now.', 'Weather conditions are suitable for rice planting.', $lowYield ? 'Expected harvest is low, so consider adjusting your planting schedule.' : 'Keep checking the weather and make sure the field has enough water.']);
        }

        return $tagalog
            ? ['Puwede ka nang magtanim, pero patuloy na bantayan ang panahon.', 'Walang malaking problema sa panahon na inaasahan.', $lowYield ? 'Mababa ang inaasahang ani, kaya isipin ang pag-ayos ng iskedyul ng pagtatanim.' : 'Siguraduhing may sapat na tubig ang bukid.']
            : ['You can plant now, but keep checking the weather.', 'No major weather problem is expected.', $lowYield ? 'Expected harvest is low, so consider adjusting your planting schedule.' : 'Make sure the field has enough water.'];
    }

    /** @return list<string> */
    private static function irrigation(string $recommendation, bool $tagalog): array
    {
        $en = [
            'Increase irrigation or provide supplemental water support.' => ['Give your field more water.', 'Rainfall is low.', 'Irrigate regularly so the field does not dry out.'],
            'Monitor soil moisture closely.' => ['Check your field water often.', 'Rainfall may not be enough.', 'Make sure the field has enough water.'],
            'Irrigation is not necessary right now.' => ["You don't need to irrigate right now.", 'There is enough rain.', 'Check that the field does not stay too wet.'],
            'Reduce irrigation to avoid waterlogging.' => ['Use less irrigation water.', 'There is already a lot of rain.', 'Keep drainage open so water does not stay in the field too long.'],
            'Maintain the irrigation schedule.' => ['Keep your usual irrigation schedule.', 'Rainfall is low this month.', 'Make sure the field has enough water.'],
            'Maintain normal irrigation monitoring.' => ['Keep your usual watering routine.', 'Rainfall is in a normal range.', 'Check the field water from time to time.'],
        ];
        $tl = [
            'Increase irrigation or provide supplemental water support.' => ['Dagdagan ang tubig sa bukid.', 'Mababa ang ulan.', 'Magpatubig nang regular para hindi matuyo ang bukid.'],
            'Monitor soil moisture closely.' => ['Madalas na tingnan ang tubig sa bukid.', 'Maaaring hindi sapat ang ulan.', 'Siguraduhing may sapat na tubig ang bukid.'],
            'Irrigation is not necessary right now.' => ['Hindi mo kailangang magpatubig ngayon.', 'Sapat ang ulan.', 'Tingnan na hindi masyadong basa ang bukid.'],
            'Reduce irrigation to avoid waterlogging.' => ['Bawasan ang patubig.', 'Marami nang ulan.', 'Panatilihing bukas ang drainage para hindi tumagal ang tubig sa bukid.'],
            'Maintain the irrigation schedule.' => ['Ituloy ang karaniwang iskedyul ng patubig.', 'Mababa ang ulan ngayong buwan.', 'Siguraduhing may sapat na tubig ang bukid.'],
            'Maintain normal irrigation monitoring.' => ['Ituloy ang karaniwang pagpapatubig.', 'Normal ang dami ng ulan.', 'Tingnan paminsan-minsan ang tubig sa bukid.'],
        ];

        return ($tagalog ? $tl : $en)[$recommendation] ?? [$recommendation];
    }

    /** @return list<string>|null */
    private static function riceYield(array $yield, bool $tagalog): ?array
    {
        $value = $yield['predicted_yield'] ?? null;
        if (! is_numeric($value)) {
            return null;
        }
        $value = (float) $value;
        $total = is_numeric($yield['estimated_total_production_tons'] ?? null) ? (float) $yield['estimated_total_production_tons'] : null;
        $answer = $tagalog
            ? 'Ang inaasahang ani mo ay mga '.number_format($value, 1).' tonelada bawat ektarya'.($total !== null ? ' (mga '.number_format($total, 1).' tonelada para sa bukid mo)' : '').'.'
            : 'Your expected harvest is about '.number_format($value, 1).' tons per hectare'.($total !== null ? ' (about '.number_format($total, 1).' tons for your farm)' : '').'.';

        // Same bands as DecisionSupportService::yieldAdvisory().
        [$reason, $action] = match (true) {
            $value < 2 => $tagalog ? ['Napakababa nito.', 'Humingi agad ng tulong sa Municipal Agriculture Office.'] : ['This is very low.', 'Ask the Municipal Agriculture Office for help soon.'],
            $value < 3 => $tagalog ? ['Mas mababa ito kaysa karaniwan.', 'Magtanong sa MAO tungkol sa soil testing at tamang oras ng pag-abono.'] : ['This is lower than usual.', 'Ask the MAO about soil testing and the right time to apply fertilizer.'],
            $value < 4 => $tagalog ? ['Karaniwan ang ani na ito.', 'Patuloy na bantayan ang panahon at ang palay.'] : ['This is an average harvest.', 'Keep watching the weather and your crop.'],
            default => $tagalog ? ['Magandang ani ito.', 'Ituloy ang maayos na pag-aalaga sa bukid.'] : ['This is a good harvest outlook.', 'Keep up good care of your field.'],
        };

        return [$answer, $reason, $action];
    }

    /** @return list<string>|null */
    private static function weather(array $weather, bool $tagalog): ?array
    {
        $rainfall = is_numeric($weather['rainfall'] ?? null) ? (float) $weather['rainfall'] : null;
        if ($rainfall === null) {
            return null;
        }
        [$reason, $action] = self::rainMeaning($rainfall, $tagalog);

        return [$tagalog ? self::number($rainfall).' mm na ulan ang inaasahan ngayong buwan.' : self::number($rainfall).' mm of rain is expected this month.', $reason, $action];
    }

    /** @return list<string> */
    private static function climateRisk(array $warnings, bool $tagalog): array
    {
        $titles = collect($warnings)->pluck('title')->filter()->unique()->values();
        if ($titles->isEmpty()) {
            return $tagalog
                ? ['Walang malaking babala sa panahon ngayon.', 'Patuloy na bantayan ang panahon.']
                : ['There is no major weather warning right now.', 'Keep checking the weather.'];
        }

        return $tagalog
            ? ['Mag-ingat sa: '.$titles->implode(', ').'.', 'Puwede itong makaapekto sa palay mo.', 'Panatilihing bukas ang drainage at tingnan ang bukid pagkatapos ng malakas na ulan.']
            : ['Watch out for: '.$titles->implode(', ').'.', 'This can affect your rice field.', 'Keep drainage open and check the field after heavy rain.'];
    }

    /** @return list<string>|null */
    private static function farmingAdvice(array $insights, bool $tagalog): ?array
    {
        $steps = array_slice((array) ($insights['next_steps'] ?? []), 0, 2);

        return $steps === [] ? null : [$tagalog ? 'Ito ang puwede mong gawin:' : 'Here is what you can do:', ...$steps];
    }

    /** @return array{0: string, 1: string} reason and action for a month's rainfall (same bands as the planting rules) */
    private static function rainMeaning(?float $rainfall, bool $tagalog): array
    {
        return match (true) {
            $rainfall === null => $tagalog ? ['', 'Patuloy na bantayan ang panahon.'] : ['', 'Keep checking the weather.'],
            $rainfall > 350 => $tagalog ? ['Malakas na ulan ito; puwedeng bumaha ang bukid.', 'Panatilihing bukas ang drainage.'] : ['That is heavy rain, and fields may flood.', 'Keep drainage canals open.'],
            $rainfall > 280 => $tagalog ? ['Mas marami ito kaysa sa kailangan ng palay.', 'Tingnan ang drainage bago magtanim.'] : ['That is more than the rice needs.', 'Check field drainage before planting.'],
            $rainfall >= 120 => $tagalog ? ['Sapat ito para sa palay.', 'Ituloy ang normal na gawain sa bukid.'] : ['That is enough rain for rice.', 'Continue normal farm work.'],
            $rainfall >= 80 => $tagalog ? ['Medyo kulang ito para sa palay.', 'Siguraduhing may sapat na tubig ang bukid.'] : ['That is a little low for rice.', 'Make sure the field has enough water.'],
            default => $tagalog ? ['Mababa ito para sa palay.', 'Maghanda ng patubig, lalo na sa bukid na umaasa sa ulan.'] : ['That is low for rice.', 'Prepare irrigation, especially for rain-fed fields.'],
        };
    }

    /** First line is the answer; the reason and action follow in one short paragraph. */
    private static function format(array $lines, bool $tagalog): string
    {
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));
        $answer = array_shift($lines);

        return trim($answer.($lines ? "\n".implode(' ', $lines) : ''));
    }
}
