<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('redirects to text input page when there is no processed schedule in session', function (): void {
    $response = $this->get(route('schedules.index'));

    $response->assertRedirect(route('turn-selection-export.index'));
});

it('renders schedule explorer when processed file exists in session', function (): void {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Schedules');

    $headers = ['ID', 'API_T', 'API_PL', 'ES_TP', 'ES_PL', 'IA_PL', 'SI_PL', 'SBD_PL', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex'];

    foreach ($headers as $index => $header) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $header);
    }

    $sheet->setCellValue('A2', '5');
    $sheet->setCellValue('B2', 'T1');
    $sheet->setCellValue('C2', 'PL2');
    $sheet->setCellValue('D2', 'TP1');
    $sheet->setCellValue('E2', 'PL2');
    $sheet->setCellValue('F2', 'PL2');
    $sheet->setCellValue('G2', 'PL2');
    $sheet->setCellValue('H2', 'PL2');
    $sheet->setCellValue('I2', '08:00-10:00 API T1');
    $sheet->setCellValue('J2', '11:00-14:00 IA PL2 | 16:00-18:00 ES TP1');
    $sheet->setCellValue('K2', '11:00-14:00 SI PL2');
    $sheet->setCellValue('L2', '09:00-11:00 API PL2 | 10:00-13:00 SBD PL2');
    $sheet->setCellValue('M2', '17:00-19:00 SBD OBR');

    $path = storage_path('app/private/tests/schedules-render.xlsx');

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    (new Xlsx($spreadsheet))->save($path);

    $response = $this->withSession([
        'schedules.upload.path' => $path,
        'schedules.upload.original_name' => 'schedules-render.xlsx',
    ])->get(route('schedules.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Schedules/Index')
        ->where('meta.totalSchedules', 1)
        ->where('meta.totalUnits', 5)
        ->has('schedules', 1)
        ->has('units', 5));
});

it('renders schedule explorer directly from text rows in session', function (): void {
    $response = $this->withSession([
        'schedules.text.rows' => [
            [
                'schedule_key' => 'Selecao Manual (texto)',
                'unit' => 'ALNET',
                'option_code' => 'PL1',
                'class_type' => 'PL',
                'day' => 'TER',
                'day_index' => 2,
                'start' => '09:30',
                'end' => '12:30',
                'mandatory' => false,
            ],
            [
                'schedule_key' => 'Selecao Manual (texto)',
                'unit' => 'ALNET',
                'option_code' => 'PL4',
                'class_type' => 'PL',
                'day' => 'QUI',
                'day_index' => 4,
                'start' => '09:30',
                'end' => '12:30',
                'mandatory' => false,
            ],
        ],
    ])->get(route('schedules.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Schedules/Index')
        ->where('meta.sheetName', 'TextInput')
        ->where('meta.uploadedFileName', 'input-texto')
        ->where('meta.totalSchedules', 2)
        ->where('meta.totalUnits', 1)
        ->has('schedules', 2)
        ->has('units', 1));
});

it('keeps all input options visible even when some are not in generated schedules', function (): void {
    $response = $this->withSession([
        'schedules.text.rows' => [
            [
                'schedule_key' => 'Selecao Manual (texto)',
                'unit' => 'AI',
                'option_code' => 'OBR',
                'class_type' => 'OBR',
                'day' => 'SEG',
                'day_index' => 1,
                'start' => '09:30',
                'end' => '09:45',
                'mandatory' => true,
            ],
            [
                'schedule_key' => 'Selecao Manual (texto)',
                'unit' => 'AI',
                'option_code' => 'PL1',
                'class_type' => 'PL',
                'day' => 'SEG',
                'day_index' => 1,
                'start' => '07:00',
                'end' => '08:00',
                'mandatory' => false,
            ],
            [
                'schedule_key' => 'Selecao Manual (texto)',
                'unit' => 'AI',
                'option_code' => 'PL2',
                'class_type' => 'PL',
                'day' => 'SEG',
                'day_index' => 1,
                'start' => '09:00',
                'end' => '10:00',
                'mandatory' => false,
            ],
        ],
    ])->get(route('schedules.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Schedules/Index')
        ->where('meta.totalSchedules', 1)
        ->where('meta.totalUnits', 1)
        ->has('schedules', 1)
        ->has('units', 1)
        ->has('units.0.options', 2));
});
