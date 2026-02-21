<?php

declare(strict_types=1);

namespace App\Actions;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final readonly class ExportTurnSelectionSpreadsheetAction
{
    /**
     * @param array<string, string> $selections
     * @param array<int, array<string, mixed>> $schedules
     * @return array{path: string, filename: string}
     */
    public function handle(array $selections, array $schedules): array
    {
        $spreadsheet = new Spreadsheet;
        $scheduleSheet = $spreadsheet->getActiveSheet();
        $scheduleSheet->setTitle('Schedules');
        $headers = ['ID', 'API_T', 'API_PL', 'ES_TP', 'ES_PL', 'IA_PL', 'SI_PL', 'SBD_PL', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex'];

        foreach ($headers as $index => $header) {
            $scheduleSheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $header);
        }

        $scheduleRow = 2;

        foreach ($schedules as $schedule) {
            $row = $this->buildUploadCompatibleRow($schedule, $scheduleRow - 1);

            foreach ($headers as $index => $header) {
                $scheduleSheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$scheduleRow, (string) ($row[$header] ?? ''));
            }

            $scheduleRow++;
        }

        $selectionSheet = $spreadsheet->createSheet();
        $selectionSheet->setTitle('Selections');
        $selectionSheet->fromArray(['Chave', 'Turno'], null, 'A1');

        $selectionRow = 2;

        foreach ($selections as $key => $value) {
            $selectionSheet->setCellValue('A'.$selectionRow, $key);
            $selectionSheet->setCellValue('B'.$selectionRow, $value);
            $selectionRow++;
        }

        $eventsSheet = $spreadsheet->createSheet();
        $eventsSheet->setTitle('Events');
        $eventsSheet->fromArray(['Schedule', 'UC', 'Tipo', 'Turno', 'Dia', 'Inicio', 'Fim', 'Obrigatorio'], null, 'A1');

        $eventsRow = 2;

        foreach ($schedules as $schedule) {
            foreach (($schedule['events'] ?? []) as $event) {
                $eventsSheet->setCellValue('A'.$eventsRow, (string) ($schedule['key'] ?? ''));
                $eventsSheet->setCellValue('B'.$eventsRow, (string) ($event['unit'] ?? ''));
                $eventsSheet->setCellValue('C'.$eventsRow, (string) ($event['classType'] ?? ''));
                $eventsSheet->setCellValue('D'.$eventsRow, (string) ($event['optionCode'] ?? ''));
                $eventsSheet->setCellValue('E'.$eventsRow, (string) ($event['day'] ?? ''));
                $eventsSheet->setCellValue('F'.$eventsRow, (string) ($event['start'] ?? ''));
                $eventsSheet->setCellValue('G'.$eventsRow, (string) ($event['end'] ?? ''));
                $eventsSheet->setCellValue('H'.$eventsRow, ($event['mandatory'] ?? false) ? 'sim' : 'nao');
                $eventsRow++;
            }
        }

        $fileName = 'turnos-export-'.now()->format('Ymd-His').'.xlsx';
        $directory = storage_path('app/private/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.$fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return [
            'path' => $path,
            'filename' => $fileName,
        ];
    }

    /**
     * @param array<string, mixed> $schedule
     * @return array<string, string>
     */
    private function buildUploadCompatibleRow(array $schedule, int $fallbackId): array
    {
        $events = $schedule['events'] ?? [];
        $id = $this->extractNumericId((string) ($schedule['key'] ?? ''), $fallbackId);

        $row = [
            'ID' => (string) $id,
            'API_T' => '',
            'API_PL' => '',
            'ES_TP' => '',
            'ES_PL' => '',
            'IA_PL' => '',
            'SI_PL' => '',
            'SBD_PL' => '',
            'Seg' => '',
            'Ter' => '',
            'Qua' => '',
            'Qui' => '',
            'Sex' => '',
        ];

        $dayEvents = ['Seg' => [], 'Ter' => [], 'Qua' => [], 'Qui' => [], 'Sex' => []];

        foreach ($events as $event) {
            $day = $this->normalizeDayShort($event);
            $unit = strtoupper((string) ($event['unit'] ?? ''));
            $classType = strtoupper((string) ($event['classType'] ?? ''));
            $optionCode = strtoupper((string) ($event['optionCode'] ?? ''));
            $mandatory = (bool) ($event['mandatory'] ?? false);
            $start = (string) ($event['start'] ?? '');
            $end = (string) ($event['end'] ?? '');

            if (array_key_exists($day, $dayEvents) && $start !== '' && $end !== '') {
                $name = $mandatory && $optionCode === 'OBR'
                    ? trim($unit.' OBR')
                    : trim($unit.' '.($optionCode !== '' ? $optionCode : $classType));

                $dayEvents[$day][] = [
                    'start' => $start,
                    'end' => $end,
                    'name' => $name,
                ];
            }

            if ($mandatory) {
                continue;
            }

            $field = match ([$unit, $this->normalizeClassType($classType)]) {
                ['API', 'T'] => 'API_T',
                ['API', 'PL'] => 'API_PL',
                ['ES', 'TP'] => 'ES_TP',
                ['ES', 'PL'] => 'ES_PL',
                ['IA', 'PL'] => 'IA_PL',
                ['SI', 'PL'] => 'SI_PL',
                ['SBD', 'PL'] => 'SBD_PL',
                default => null,
            };

            if (is_string($field) && $optionCode !== '' && $row[$field] === '') {
                $row[$field] = $optionCode;
            }
        }

        foreach ($dayEvents as $day => $list) {
            usort($list, fn (array $left, array $right): int => $left['start'] <=> $right['start']);
            $row[$day] = implode(' | ', array_map(fn (array $event): string => $event['start'].'-'.$event['end'].' '.$event['name'], $list));
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function normalizeDayShort(array $event): string
    {
        $dayIndex = (int) ($event['dayIndex'] ?? 0);

        if ($dayIndex >= 1 && $dayIndex <= 5) {
            return ['Seg', 'Ter', 'Qua', 'Qui', 'Sex'][$dayIndex - 1];
        }

        $day = mb_strtoupper(trim((string) ($event['day'] ?? '')));

        return match ($day) {
            'SEG', 'SEGUNDA' => 'Seg',
            'TER', 'TERCA', 'TERÇA' => 'Ter',
            'QUA', 'QUARTA' => 'Qua',
            'QUI', 'QUINTA' => 'Qui',
            'SEX', 'SEXTA' => 'Sex',
            default => 'Seg',
        };
    }

    private function normalizeClassType(string $classType): string
    {
        if (str_starts_with($classType, 'TP')) {
            return 'TP';
        }

        if (str_starts_with($classType, 'PL')) {
            return 'PL';
        }

        return 'T';
    }

    private function extractNumericId(string $value, int $fallback): int
    {
        if (preg_match('/\d+/', $value, $matches) === 1) {
            return (int) $matches[0];
        }

        return $fallback;
    }
}
