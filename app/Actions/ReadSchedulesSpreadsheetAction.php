<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

final readonly class ReadSchedulesSpreadsheetAction
{
    /**
     * @return array{
     *     rows: array<int, array{
     *         schedule_key: string,
     *         unit: string,
     *         option_code: string,
     *         class_type: string,
     *         day: string,
     *         day_index: int,
     *         start: string,
     *         end: string,
     *         mandatory: bool
     *     }>,
     *     meta: array{
     *         filePath: string,
     *         missingFile: bool,
     *         missingDependency: bool,
     *         parseError: bool,
     *         parseErrorMessage: string|null,
     *         sheetName: string,
     *         sourceRows: int
     *     }
     * }
     */
    public function handle(?string $filePath = null): array
    {
        $resolvedPath = $filePath ?? storage_path('app/horarios_sem_sobreposicao.xlsx');

        if (! is_file($resolvedPath)) {
            return [
                'rows' => [],
                'meta' => [
                    'filePath' => $resolvedPath,
                    'missingFile' => true,
                    'missingDependency' => false,
                    'parseError' => false,
                    'parseErrorMessage' => null,
                    'sheetName' => 'Schedules',
                    'sourceRows' => 0,
                ],
            ];
        }

        if (! class_exists(IOFactory::class)) {
            return [
                'rows' => [],
                'meta' => [
                    'filePath' => $resolvedPath,
                    'missingFile' => false,
                    'missingDependency' => true,
                    'parseError' => false,
                    'parseErrorMessage' => null,
                    'sheetName' => 'Schedules',
                    'sourceRows' => 0,
                ],
            ];
        }

        try {
            $spreadsheet = IOFactory::load($resolvedPath);
        } catch (Throwable $throwable) {
            return [
                'rows' => [],
                'meta' => [
                    'filePath' => $resolvedPath,
                    'missingFile' => false,
                    'missingDependency' => false,
                    'parseError' => true,
                    'parseErrorMessage' => $throwable->getMessage(),
                    'sheetName' => 'Schedules',
                    'sourceRows' => 0,
                ],
            ];
        }

        $sheet = $spreadsheet->getSheetByName('Events')
            ?? $spreadsheet->getSheetByName('Schedules')
            ?? $spreadsheet->getActiveSheet();
        $sheetName = $sheet->getTitle();
        $rows = $sheet->toArray(null, true, true, true);

        if ($rows === []) {
            return [
                'rows' => [],
                'meta' => [
                    'filePath' => $resolvedPath,
                    'missingFile' => false,
                    'missingDependency' => false,
                    'parseError' => false,
                    'parseErrorMessage' => null,
                    'sheetName' => $sheetName,
                    'sourceRows' => 0,
                ],
            ];
        }

        /** @var array<string, mixed> $header */
        $header = array_shift($rows);
        $headerMap = $this->buildHeaderMap($header);
        $normalizedColumns = $this->buildNormalizedColumnMap($header);

        $parsedRows = $this->looksLikeLegacyScheduleFormat($normalizedColumns)
            ? $this->parseLegacyScheduleRows($rows, $normalizedColumns)
            : $this->parseStructuredRows($rows, $headerMap);

        return [
            'rows' => $parsedRows,
            'meta' => [
                'filePath' => $resolvedPath,
                'missingFile' => false,
                'missingDependency' => false,
                'parseError' => false,
                'parseErrorMessage' => null,
                'sheetName' => $sheetName,
                'sourceRows' => count($parsedRows),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $header
     * @return array<string, string>
     */
    private function buildHeaderMap(array $header): array
    {
        $aliases = [
            'schedule' => ['schedule', 'horario', 'combinacao', 'combination', 'id'],
            'unit' => ['uc', 'unidadecurricular', 'unidade', 'disciplina', 'course', 'subject'],
            'option' => ['turno', 'turma', 'shift', 'option', 'class'],
            'type' => ['tipo', 'type', 'component'],
            'day' => ['dia', 'day'],
            'start' => ['inicio', 'inicial', 'start', 'hora_inicio', 'starttime'],
            'end' => ['fim', 'final', 'end', 'hora_fim', 'endtime'],
            'mandatory' => ['obrigatorio', 'mandatory', 'fixo', 'required'],
        ];

        $normalizedColumns = $this->buildNormalizedColumnMap($header);
        $mapping = [];

        foreach ($aliases as $key => $keys) {
            foreach ($keys as $alias) {
                if (array_key_exists($alias, $normalizedColumns)) {
                    $mapping[$key] = $normalizedColumns[$alias];

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param array<string, mixed> $header
     * @return array<string, string>
     */
    private function buildNormalizedColumnMap(array $header): array
    {
        $normalizedColumns = [];

        foreach ($header as $column => $value) {
            $normalizedColumns[$this->normalizeHeader((string) $value)] = (string) $column;
        }

        return $normalizedColumns;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $headerMap
     * @return array<int, array{schedule_key: string, unit: string, option_code: string, class_type: string, day: string, day_index: int, start: string, end: string, mandatory: bool}>
     */
    private function parseStructuredRows(array $rows, array $headerMap): array
    {
        $normalizedRows = [];

        foreach ($rows as $row) {
            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            $day = $this->normalizeDay($this->extractCell($row, $headerMap, 'day'));
            $start = $this->normalizeTime($this->extractCell($row, $headerMap, 'start'));
            $end = $this->normalizeTime($this->extractCell($row, $headerMap, 'end'));
            $unit = $this->normalizeText($this->extractCell($row, $headerMap, 'unit'));

            if ($day === null || $start === null || $end === null || $unit === '') {
                continue;
            }

            $scheduleKey = $this->normalizeText($this->extractCell($row, $headerMap, 'schedule'));
            $optionCode = $this->normalizeText($this->extractCell($row, $headerMap, 'option'));
            $classType = $this->normalizeText($this->extractCell($row, $headerMap, 'type'));
            $mandatoryRaw = $this->extractCell($row, $headerMap, 'mandatory');

            $mandatory = $this->normalizeBool($mandatoryRaw) || $optionCode === '';

            $normalizedRows[] = [
                'schedule_key' => $scheduleKey !== '' ? $scheduleKey : 'Sem combinacao',
                'unit' => $unit,
                'option_code' => $optionCode !== '' ? $optionCode : 'Obrigatorio',
                'class_type' => $classType,
                'day' => $day['name'],
                'day_index' => $day['index'],
                'start' => $start,
                'end' => $end,
                'mandatory' => $mandatory,
            ];
        }

        return $normalizedRows;
    }

    /**
     * @param array<string, string> $columns
     */
    private function looksLikeLegacyScheduleFormat(array $columns): bool
    {
        $required = ['id', 'seg', 'ter', 'qua', 'qui', 'sex'];

        foreach ($required as $column) {
            if (! array_key_exists($column, $columns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $columns
     * @return array<int, array{schedule_key: string, unit: string, option_code: string, class_type: string, day: string, day_index: int, start: string, end: string, mandatory: bool}>
     */
    private function parseLegacyScheduleRows(array $rows, array $columns): array
    {
        $idColumn = $columns['id'];
        $dayColumns = [
            'Seg' => $columns['seg'],
            'Ter' => $columns['ter'],
            'Qua' => $columns['qua'],
            'Qui' => $columns['qui'],
            'Sex' => $columns['sex'],
        ];

        $fieldColumns = [
            'API_T' => $columns['apit'] ?? null,
            'API_PL' => $columns['apipl'] ?? null,
            'ES_TP' => $columns['estp'] ?? null,
            'ES_PL' => $columns['espl'] ?? null,
            'IA_PL' => $columns['iapl'] ?? null,
            'SI_PL' => $columns['sipl'] ?? null,
            'SBD_PL' => $columns['sbdpl'] ?? null,
        ];

        $normalizedRows = [];

        foreach ($rows as $row) {
            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            $scheduleKey = $this->normalizeText($row[$idColumn] ?? null);

            if ($scheduleKey === '') {
                continue;
            }

            $selectedTurns = [];

            foreach ($fieldColumns as $field => $column) {
                if (! is_string($column)) {
                    continue;
                }

                $value = $this->normalizeText($row[$column] ?? null);

                if ($value !== '') {
                    $selectedTurns[$field] = $value;
                }
            }

            foreach ($dayColumns as $day => $column) {
                $cellValue = $this->normalizeText($row[$column] ?? null);

                foreach ($this->parseLegacyCell($cellValue, $day) as $event) {
                    $turnField = $this->detectTurnFieldFromEventName($event['name']);
                    $unit = $this->detectUnitFromEventName($event['name']);
                    $classType = $this->detectClassTypeFromEventName($event['name']);

                    $mandatory = true;
                    $optionCode = $this->detectOptionFromEventName($event['name']) ?? 'Obrigatorio';

                    if ($turnField !== null && array_key_exists($turnField, $selectedTurns)) {
                        $mandatory = false;
                        $optionCode = $selectedTurns[$turnField];
                    }

                    $normalizedRows[] = [
                        'schedule_key' => $scheduleKey,
                        'unit' => $unit,
                        'option_code' => $optionCode,
                        'class_type' => $classType,
                        'day' => $day,
                        'day_index' => $this->dayIndexFromShortName($day),
                        'start' => $event['startHm'],
                        'end' => $event['endHm'],
                        'mandatory' => $mandatory,
                    ];
                }
            }
        }

        return $normalizedRows;
    }

    /**
     * @return array<int, array{name: string, startHm: string, endHm: string}>
     */
    private function parseLegacyCell(string $cell, string $day): array
    {
        if ($cell === '' || mb_strtolower($cell) === 'nan') {
            return [];
        }

        $events = [];
        $parts = array_map('trim', explode('|', $cell));

        foreach ($parts as $part) {
            if (preg_match('/^(?<start>\d{2}:\d{2})-(?<end>\d{2}:\d{2})\s+(?<name>.+)$/', $part, $matches) !== 1) {
                continue;
            }

            $events[] = [
                'day' => $day,
                'name' => trim($matches['name']),
                'startHm' => $matches['start'],
                'endHm' => $matches['end'],
            ];
        }

        return $events;
    }

    private function detectTurnFieldFromEventName(string $name): ?string
    {
        $normalized = mb_strtolower(trim($name));

        if (str_starts_with($normalized, 'api t')) {
            return 'API_T';
        }

        if (str_starts_with($normalized, 'api pl')) {
            return 'API_PL';
        }

        if (str_starts_with($normalized, 'es tp')) {
            return 'ES_TP';
        }

        if (str_starts_with($normalized, 'es pl')) {
            return 'ES_PL';
        }

        if (str_starts_with($normalized, 'ia pl')) {
            return 'IA_PL';
        }

        if (str_starts_with($normalized, 'si pl')) {
            return 'SI_PL';
        }

        if (str_starts_with($normalized, 'sbd pl')) {
            return 'SBD_PL';
        }

        return null;
    }

    private function detectUnitFromEventName(string $name): string
    {
        if (preg_match('/^(API|ES|IA|SI|SBD)\b/i', trim($name), $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return 'Outros';
    }

    private function detectClassTypeFromEventName(string $name): string
    {
        $normalized = mb_strtoupper(trim($name));

        if (str_contains($normalized, ' TP')) {
            return 'TP';
        }

        if (str_contains($normalized, ' PL')) {
            return 'PL';
        }

        if (str_contains($normalized, ' IAT')) {
            return 'T';
        }

        if (preg_match('/\bT\d*\b/', $normalized) === 1) {
            return 'T';
        }

        if (str_contains($normalized, 'OBR')) {
            return 'OBR';
        }

        return 'OUTRO';
    }

    private function detectOptionFromEventName(string $name): ?string
    {
        if (preg_match('/\b((?:PL|TP|T|IAT)\d+|OBR)\b/i', trim($name), $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    private function dayIndexFromShortName(string $day): int
    {
        return match ($day) {
            'Seg' => 1,
            'Ter' => 2,
            'Qua' => 3,
            'Qui' => 4,
            'Sex' => 5,
            'Sab' => 6,
            default => 0,
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractCell(array $row, array $headerMap, string $key): mixed
    {
        $column = $headerMap[$key] ?? null;

        if ($column === null) {
            return null;
        }

        return $row[$column] ?? null;
    }

    private function normalizeHeader(string $value): string
    {
        return (string) Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->trim();
    }

    private function normalizeText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'sim', 'yes', 'true', 'x', 'obrigatorio', 'mandatory'], true);
    }

    /**
     * @return array{name: string, index: int}|null
     */
    private function normalizeDay(mixed $value): ?array
    {
        $raw = mb_strtolower(trim((string) $value));

        if ($raw === '') {
            return null;
        }

        $map = [
            'seg' => ['Segunda', 1],
            'segunda' => ['Segunda', 1],
            'segunda-feira' => ['Segunda', 1],
            'monday' => ['Segunda', 1],
            'ter' => ['Terca', 2],
            'terca' => ['Terca', 2],
            'terça' => ['Terca', 2],
            'terca-feira' => ['Terca', 2],
            'terça-feira' => ['Terca', 2],
            'tuesday' => ['Terca', 2],
            'qua' => ['Quarta', 3],
            'quarta' => ['Quarta', 3],
            'quarta-feira' => ['Quarta', 3],
            'wednesday' => ['Quarta', 3],
            'qui' => ['Quinta', 4],
            'quinta' => ['Quinta', 4],
            'quinta-feira' => ['Quinta', 4],
            'thursday' => ['Quinta', 4],
            'sex' => ['Sexta', 5],
            'sexta' => ['Sexta', 5],
            'sexta-feira' => ['Sexta', 5],
            'friday' => ['Sexta', 5],
            'sab' => ['Sabado', 6],
            'sáb' => ['Sabado', 6],
            'sabado' => ['Sabado', 6],
            'sábado' => ['Sabado', 6],
            'saturday' => ['Sabado', 6],
            'dom' => ['Domingo', 7],
            'domingo' => ['Domingo', 7],
            'sunday' => ['Domingo', 7],
        ];

        if (! array_key_exists($raw, $map)) {
            return null;
        }

        return [
            'name' => $map[$raw][0],
            'index' => $map[$raw][1],
        ];
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('H:i');
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        if (preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2})/', $stringValue, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches['hour'], (int) $matches['minute']);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
