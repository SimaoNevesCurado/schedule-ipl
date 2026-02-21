<?php

declare(strict_types=1);

use App\Actions\GenerateAutomaticSchedulesFromTextRowsAction;

it('builds non-overlapping automatic schedules from text rows', function (): void {
    $action = new GenerateAutomaticSchedulesFromTextRowsAction;

    $rows = [
        [
            'schedule_key' => 'Selecao Manual (texto)',
            'unit' => 'API',
            'option_code' => 'T1',
            'class_type' => 'T',
            'day' => 'SEG',
            'day_index' => 1,
            'start' => '08:00',
            'end' => '10:00',
            'mandatory' => false,
        ],
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
    ];

    $generatedRows = $action->handle($rows);

    $scheduleKeys = array_values(array_unique(array_map(fn (array $row): string => $row['schedule_key'], $generatedRows)));

    expect($scheduleKeys)->toHaveCount(2);
    expect($scheduleKeys[0])->toBe('Auto 001');
    expect($scheduleKeys[1])->toBe('Auto 002');
});

it('does not bias all generated schedules to the first option of a group', function (): void {
    $action = new GenerateAutomaticSchedulesFromTextRowsAction;

    $rows = [
        [
            'schedule_key' => 'Selecao Manual (texto)',
            'unit' => 'AI',
            'option_code' => 'PL2',
            'class_type' => 'PL',
            'day' => 'TER',
            'day_index' => 2,
            'start' => '11:00',
            'end' => '14:00',
            'mandatory' => false,
        ],
        [
            'schedule_key' => 'Selecao Manual (texto)',
            'unit' => 'AI',
            'option_code' => 'PL3',
            'class_type' => 'PL',
            'day' => 'TER',
            'day_index' => 2,
            'start' => '17:00',
            'end' => '20:00',
            'mandatory' => false,
        ],
        [
            'schedule_key' => 'Selecao Manual (texto)',
            'unit' => 'ES',
            'option_code' => 'TP1',
            'class_type' => 'TP',
            'day' => 'SEX',
            'day_index' => 5,
            'start' => '08:00',
            'end' => '10:00',
            'mandatory' => false,
        ],
        [
            'schedule_key' => 'Selecao Manual (texto)',
            'unit' => 'ES',
            'option_code' => 'TP2',
            'class_type' => 'TP',
            'day' => 'SEX',
            'day_index' => 5,
            'start' => '10:00',
            'end' => '12:00',
            'mandatory' => false,
        ],
    ];

    $generatedRows = $action->handle($rows, 20);

    $aiOptions = array_values(array_unique(array_map(
        fn (array $row): string => $row['option_code'],
        array_filter($generatedRows, fn (array $row): bool => $row['unit'] === 'AI'),
    )));

    sort($aiOptions);

    expect($aiOptions)->toBe(['PL2', 'PL3']);
});
