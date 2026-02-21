<?php

declare(strict_types=1);

use App\Actions\ReadSchedulesSpreadsheetAction;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('parses legacy abc spreadsheet format', function (): void {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Schedules');

    $headers = ['ID', 'API_T', 'API_PL', 'ES_TP', 'ES_PL', 'IA_PL', 'SI_PL', 'SBD_PL', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex'];

    foreach ($headers as $index => $header) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $header);
    }

    $sheet->setCellValue('A2', '1');
    $sheet->setCellValue('B2', 'T1');
    $sheet->setCellValue('C2', 'PL2');
    $sheet->setCellValue('D2', 'TP1');
    $sheet->setCellValue('E2', 'PL2');
    $sheet->setCellValue('F2', 'PL2');
    $sheet->setCellValue('G2', 'PL2');
    $sheet->setCellValue('H2', 'PL2');
    $sheet->setCellValue('I2', '08:00-10:00 API T1 | 15:00-18:00 ES PL2');
    $sheet->setCellValue('J2', '11:00-14:00 IA PL2');
    $sheet->setCellValue('K2', '13:00-14:00 API T1');
    $sheet->setCellValue('L2', '09:00-11:00 API PL2');
    $sheet->setCellValue('M2', '17:00-19:00 SBD OBR');

    $path = storage_path('app/private/tests/legacy-format.xlsx');

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    $writer = new Xlsx($spreadsheet);
    $writer->save($path);

    $action = new ReadSchedulesSpreadsheetAction;
    $result = $action->handle($path);

    expect($result['meta']['parseError'])->toBeFalse();
    expect($result['rows'])->not->toBeEmpty();

    $apiTheory = collect($result['rows'])->first(fn (array $row): bool => $row['unit'] === 'API' && $row['class_type'] === 'T' && $row['mandatory'] === false);
    $sbdMandatory = collect($result['rows'])->first(fn (array $row): bool => $row['unit'] === 'SBD' && $row['mandatory'] === true);

    expect($apiTheory)->not->toBeNull();
    expect($apiTheory['option_code'])->toBe('T1');

    expect($sbdMandatory)->not->toBeNull();
    expect($sbdMandatory['option_code'])->toBe('OBR');
});

it('prefers events sheet to preserve all text-exported turns', function (): void {
    $spreadsheet = new Spreadsheet;

    $schedules = $spreadsheet->getActiveSheet();
    $schedules->setTitle('Schedules');
    $schedules->fromArray(['ID', 'API_T', 'API_PL', 'ES_TP', 'ES_PL', 'IA_PL', 'SI_PL', 'SBD_PL', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex'], null, 'A1');
    $schedules->fromArray(['1', 'T1', 'PL2', '', '', '', '', '', '08:00-10:00 API T1', '', '', '', ''], null, 'A2');

    $events = $spreadsheet->createSheet();
    $events->setTitle('Events');
    $events->fromArray(['Schedule', 'UC', 'Tipo', 'Turno', 'Dia', 'Inicio', 'Fim', 'Obrigatorio'], null, 'A1');
    $events->fromArray(['Selecao Manual (texto)', 'ALNET', 'PL', 'PL7', 'Qui', '15:00', '18:00', 'nao'], null, 'A2');
    $events->fromArray(['Selecao Manual (texto)', 'API', 'T', 'T1', 'Seg', '08:00', '10:00', 'nao'], null, 'A3');

    $path = storage_path('app/private/tests/events-preferred-format.xlsx');

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    (new Xlsx($spreadsheet))->save($path);

    $action = new ReadSchedulesSpreadsheetAction;
    $result = $action->handle($path);

    expect($result['meta']['sheetName'])->toBe('Events');
    expect($result['rows'])->toHaveCount(2);

    $alnet = collect($result['rows'])->first(fn (array $row): bool => $row['unit'] === 'ALNET');

    expect($alnet)->not->toBeNull();
    expect($alnet['mandatory'])->toBeFalse();
    expect($alnet['option_code'])->toBe('PL7');
});
