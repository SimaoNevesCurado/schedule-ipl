<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class ParseTurnSelectionTextAction
{
    /**
     * @return array{
     *     selections: array<string, string>,
     *     sessions: array<int, array{day: string, start: string, end: string, unit: string, classType: string, optionCode: string|null}>,
     *     tokens: array<int, string>,
     *     hasMatches: bool
     * }
     */
    public function handle(string $text): array
    {
        $normalizedText = mb_strtoupper($text);
        $pattern = '/\b([A-Z][A-Z0-9]{1,15})\s*[-:.]?\s*(T|TP|PL|IAT|OBR)\s*[-:.]?\s*(\d+|OBR)?\b/u';
        $sessionPattern = '/\b(SEG|SEGUNDA|TER|TERCA|TERÇA|QUA|QUARTA|QUI|QUINTA|SEX|SEXTA|SAB|SÁB|SABADO|SÁBADO|DOM|DOMINGO)\s+(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})\s+([A-Z][A-Z0-9]{1,15})\s+(TP|PL|IAT|T|OBR)\s*(\d+|OBR)?\b/u';

        preg_match_all($pattern, $normalizedText, $matches, PREG_SET_ORDER);
        preg_match_all($sessionPattern, $normalizedText, $sessionMatches, PREG_SET_ORDER);

        $selections = [];
        $sessions = [];
        $tokens = [];

        foreach ($matches as $match) {
            $unit = $match[1];
            $kind = $match[2];
            $suffix = $match[3] ?? '';

            $classType = $kind === 'IAT' ? 'T' : $kind;
            $optionCode = trim($kind.($suffix !== '' && $kind !== 'OBR' ? $suffix : ''));

            if ($kind === 'OBR') {
                $classType = 'OBR';
                $optionCode = 'OBR';
            }

            $key = $unit.'_'.$classType;
            $selections[$key] = $optionCode;
            $tokens[] = trim($unit.' '.$optionCode);
        }

        foreach ($sessionMatches as $match) {
            $day = $this->normalizeDay($match[1]);
            $start = $this->normalizeTime($match[2]);
            $end = $this->normalizeTime($match[3]);
            $unit = $match[4];
            $kind = $match[5];
            $suffix = $match[6] ?? '';

            $classType = $kind === 'IAT' ? 'T' : $kind;
            $optionCode = $kind === 'OBR'
                ? 'OBR'
                : trim($kind.($suffix !== '' ? $suffix : ''));

            $sessions[] = [
                'day' => $day,
                'start' => $start,
                'end' => $end,
                'unit' => $unit,
                'classType' => $classType,
                'optionCode' => $optionCode !== '' ? $optionCode : null,
            ];

            $tokens[] = trim($day.' '.$start.'-'.$end.' '.$unit.' '.($optionCode ?? $classType));
        }

        return [
            'selections' => $selections,
            'sessions' => $sessions,
            'tokens' => array_values(array_unique($tokens)),
            'hasMatches' => $selections !== [] || $sessions !== [],
        ];
    }

    private function normalizeDay(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));

        return match ($normalized) {
            'SEG', 'SEGUNDA' => 'SEG',
            'TER', 'TERCA', 'TERÇA' => 'TER',
            'QUA', 'QUARTA' => 'QUA',
            'QUI', 'QUINTA' => 'QUI',
            'SEX', 'SEXTA' => 'SEX',
            'SAB', 'SÁB', 'SABADO', 'SÁBADO' => 'SAB',
            'DOM', 'DOMINGO' => 'DOM',
            default => $normalized,
        };
    }

    private function normalizeTime(string $value): string
    {
        [$hour, $minute] = explode(':', trim($value));

        return sprintf('%02d:%02d', (int) $hour, (int) $minute);
    }
}
