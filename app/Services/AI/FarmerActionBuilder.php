<?php

namespace App\Services\AI;

use App\Models\PlantingAdvisory;
use Illuminate\Support\Collection;

/**
 * Builds the "What to do:" action block (1-3 items) for weather, risk, and
 * planting answers, combining active PlantingAdvisory items, risk ratings,
 * weather conditions, and the farmer's crop stage.
 *
 * Typhoon or Severe risk ALWAYS comes first.
 */
final class FarmerActionBuilder
{
    /**
     * @param  array<string, mixed>  $weather  ['rainfall_mm' => ?float, 'rain_chance' => ?float, 'temperature_c' => ?float, 'condition' => ?string]
     * @param  array<string, mixed>  $risk     ['risk_level' => ?string, 'risk_type' => ?string]
     * @param  Collection<int, PlantingAdvisory>  $advisories
     */
    public static function append(
        string $body,
        FarmerChatContext $ctx,
        array $weather,
        array $risk,
        Collection $advisories,
        ?array $typhoonEvent,
        bool $tagalog,
    ): string {
        $actions = self::build($ctx, $weather, $risk, $advisories, $typhoonEvent, $tagalog);

        if ($actions === []) {
            return $body;
        }

        $heading = $tagalog ? 'Ano ang dapat gawin:' : 'What to do:';
        $lines = array_map(static fn (string $item): string => '- '.$item, $actions);

        return rtrim($body)."\n\n".$heading."\n".implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $weather
     * @param  array<string, mixed>  $risk
     * @param  Collection<int, PlantingAdvisory>  $advisories
     * @return list<string>
     */
    public static function build(
        FarmerChatContext $ctx,
        array $weather,
        array $risk,
        Collection $advisories,
        ?array $typhoonEvent,
        bool $tagalog,
    ): array {
        $actions = [];
        $stage = $ctx->cropStage;

        $riskLevel = strtolower(trim((string) ($risk['risk_level'] ?? '')));
        $riskType = strtolower(trim((string) ($risk['risk_type'] ?? '')));
        $isSevereRisk = str_contains($riskLevel, 'severe');
        $isHighRisk = str_contains($riskLevel, 'high');

        $hasTyphoonAdvisory = $typhoonEvent !== null || $advisories->contains(function (PlantingAdvisory $a): bool {
            $text = strtolower(implode(' ', array_filter([$a->title, $a->summary, $a->type])));

            return str_contains($text, 'typhoon') || str_contains($text, 'cyclone') || str_contains($text, 'bagyo') || str_contains($text, 'signal');
        });

        // 1. Typhoon or Severe risk ALWAYS comes first.
        if ($hasTyphoonAdvisory) {
            if ($stage === 'harvest') {
                $actions[] = $tagalog
                    ? 'May bagyo: anihin agad ang hinog na palay at ilagay sa ligtas at tuyong lugar.'
                    : 'Typhoon alert: harvest ripe palay right away and store it in a dry, safe place.';
            } else {
                $actions[] = $tagalog
                    ? 'May bagyo: linisin ang mga kanal ng patubig, itali ang mga gamit sa bukid, at i-update ang iyong Typhoon Safety status.'
                    : 'Typhoon alert: clear drainage canals, secure farm tools, and update your Typhoon Safety status.';
            }
        } elseif ($isSevereRisk) {
            $actions[] = $tagalog
                ? 'Matinding panganib (Severe risk): unahin ang kaligtasan, linisin ang mga kanal, at iwasan muna ang paglalagay ng abono.'
                : 'Severe risk alert: prioritize safety, clear field canals, and hold off on fertilizer today.';
        }

        // 2. Active PlantingAdvisory recommended_action (if concise and not duplicating typhoon).
        foreach ($advisories as $advisory) {
            if (count($actions) >= 3) {
                break;
            }
            $rec = trim((string) $advisory->recommended_action);
            if ($rec !== '' && ! self::isDuplicate($actions, $rec)) {
                $actions[] = rtrim($rec, '.').'.';
                break;
            }
        }

        // 3. Weather + Crop Stage rules.
        $rainMm = is_numeric($weather['rainfall_mm'] ?? null) ? (float) $weather['rainfall_mm'] : null;
        $rainChance = is_numeric($weather['rain_chance'] ?? null) ? (float) $weather['rain_chance'] : null;
        $tempC = is_numeric($weather['temperature_c'] ?? null) ? (float) $weather['temperature_c'] : null;
        $condition = strtolower((string) ($weather['condition'] ?? ''));

        $heavyRain = ($rainMm !== null && $rainMm >= 20)
            || ($rainChance !== null && $rainChance >= 70)
            || str_contains($condition, 'heavy rain')
            || str_contains($condition, 'thunderstorm')
            || str_contains($riskType, 'flood');

        $hotOrDry = ($tempC !== null && $tempC >= 32)
            || str_contains($riskType, 'drought')
            || str_contains($riskType, 'dry');

        if ($heavyRain && count($actions) < 3) {
            $actions[] = match ($stage) {
                'seedling' => $tagalog
                    ? 'Malakas ang ulan: huwag mag-abono ngayon, linisin ang kanal para hindi malunod ang punla.'
                    : 'Heavy rain expected: skip fertilizer today and clear canals so young seedlings are not submerged.',
                'harvest' => $tagalog
                    ? 'Malakas ang ulan: huwag mag-abono at ihanda ang pag-ani o pagpapatuyo ng palay sa silong.'
                    : 'Heavy rain expected: skip fertilizer and prepare to harvest or dry palay under cover.',
                default => $tagalog
                    ? 'Malakas ang ulan: huwag mag-abono ngayon at linisin ang mga kanal ng bukid.'
                    : 'Heavy rain expected: do not apply fertilizer today and clear drainage canals.',
            };
        }

        if ($hotOrDry && count($actions) < 3) {
            $actions[] = match ($stage) {
                'flowering' => $tagalog
                    ? 'Mainit sa pamumulaklak: panatilihin ang tubig (patubig) sa bukid para hindi masira ang bulaklak ng palay.'
                    : 'Hot weather during flowering: keep the water level steady so palay pollination is not stressed.',
                'seedling', 'tillering' => $tagalog
                    ? 'Mainit/tuyo ang panahon: magpatubig nang maaga sa umaga o hapon para hindi matuyo ang punla at palay.'
                    : 'Hot or dry weather: irrigate early morning or late afternoon to keep seedlings and tillers moist.',
                default => $tagalog
                    ? 'Mainit ang panahon: bantayan ang tubig sa bukid at magtrabaho nang maaga sa umaga.'
                    : 'Hot weather: monitor field moisture closely and schedule fieldwork early in the morning.',
            };
        }

        if ($isHighRisk && ! $heavyRain && ! $hotOrDry && count($actions) < 3) {
            $actions[] = $tagalog
                ? 'Mataas ang panganib sa barangay: araw-araw na tingnan ang bukid at ang lagay ng panahon.'
                : 'High risk in your barangay: check your field and local weather updates daily.';
        }

        if ($actions === []) {
            $actions[] = match ($stage) {
                'seedling' => $tagalog
                    ? 'Panatilihing tama ang lalim ng patubig para sa punla at bantayan ang mga peste.'
                    : 'Keep shallow, steady water for your seedlings and check for early pests.',
                'tillering' => $tagalog
                    ? 'Bantayan ang patubig at maglagay ng tamang pataba kung hindi malakas ang ulan.'
                    : 'Monitor field water and apply scheduled fertilizer only when rain is light.',
                'flowering' => $tagalog
                    ? 'Panatilihing may sapat na patubig ang palay habang namumulaklak.'
                    : 'Keep water level steady while your palay is in the flowering stage.',
                'harvest' => $tagalog
                    ? 'Ihanda ang pag-ani at bantayan ang tsansa ng ulan bago magpatuyo.'
                    : 'Prepare for harvest and check rain chances before drying palay.',
                default => $tagalog
                    ? 'Bantayan ang taya ng panahon at lagay ng patubig bago magtrabaho sa bukid.'
                    : 'Check the weather forecast and field water before doing fieldwork.',
            };
        }

        return array_slice($actions, 0, 3);
    }

    /** @param list<string> $existing */
    private static function isDuplicate(array $existing, string $candidate): bool
    {
        $needle = mb_strtolower(trim($candidate));
        foreach ($existing as $item) {
            if (str_contains(mb_strtolower($item), $needle) || str_contains($needle, mb_strtolower($item))) {
                return true;
            }
        }

        return false;
    }
}
