<?php

declare(strict_types=1);

use App\Actions\ExportTurnSelectionSpreadsheetAction;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports upload-compatible schedules spreadsheet format', function (): void {
    $action = new ExportTurnSelectionSpreadsheetAction;

    $result = $action->handle(
        ['API_T' => 'T1', 'API_PL' => 'PL2'],
        [[
            'key' => 'ID 77',
            'events' => [
                [
                    'unit' => 'API',
                    'optionCode' => 'T1',
                    'classType' => 'T',
                    'day' => 'Seg',
                    'dayIndex' => 1,
                    'start' => '08:00',
                    'end' => '10:00',
                    'mandatory' => false,
                ],
                [
                    'unit' => 'API',
                    'optionCode' => 'PL2',
                    'classType' => 'PL',
                    'day' => 'Qui',
                    'dayIndex' => 4,
                    'start' => '09:00',
                    'end' => '11:00',
                    'mandatory' => false,
                ],
                [
                    'unit' => 'SBD',
                    'optionCode' => 'OBR',
                    'classType' => 'OBR',
                    'day' => 'Sex',
                    'dayIndex' => 5,
                    'start' => '17:00',
                    'end' => '19:00',
                    'mandatory' => true,
                ],
            ],
            'metrics' => [
                'score' => 0,
                'daysCount' => 3,
                'gaps' => 0,
                'earliest' => '08:00',
                'latest' => '19:00',
            ],
        ]],
    );

    expect(is_file($result['path']))->toBeTrue();

    $spreadsheet = IOFactory::load($result['path']);
    $sheet = $spreadsheet->getSheetByName('Schedules');

    expect($sheet)->not->toBeNull();

    if ($sheet === null) {
        return;
    }

    expect((string) $sheet->getCell('A1')->getValue())->toBe('ID');
    expect((string) $sheet->getCell('B1')->getValue())->toBe('API_T');
    expect((string) $sheet->getCell('C1')->getValue())->toBe('API_PL');
    expect((string) $sheet->getCell('I1')->getValue())->toBe('Seg');
    expect((string) $sheet->getCell('M1')->getValue())->toBe('Sex');

    expect((string) $sheet->getCell('A2')->getValue())->toBe('77');
    expect((string) $sheet->getCell('B2')->getValue())->toBe('T1');
    expect((string) $sheet->getCell('C2')->getValue())->toBe('PL2');
    expect((string) $sheet->getCell('I2')->getValue())->toContain('08:00-10:00 API T1');
    expect((string) $sheet->getCell('M2')->getValue())->toContain('17:00-19:00 SBD OBR');

    @unlink($result['path']);
});
