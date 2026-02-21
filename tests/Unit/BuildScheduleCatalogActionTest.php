<?php

declare(strict_types=1);

use App\Actions\BuildScheduleCatalogAction;

it('builds schedule metrics and unit options', function (): void {
    $action = new BuildScheduleCatalogAction;

    $result = $action->handle([
        [
            'schedule_key' => 'A',
            'unit' => 'Algebra',
            'option_code' => 'T1',
            'class_type' => 'TP',
            'day' => 'Segunda',
            'day_index' => 1,
            'start' => '09:00',
            'end' => '10:00',
            'mandatory' => false,
        ],
        [
            'schedule_key' => 'A',
            'unit' => 'Fisica',
            'option_code' => 'OBR',
            'class_type' => 'T',
            'day' => 'Segunda',
            'day_index' => 1,
            'start' => '12:00',
            'end' => '13:00',
            'mandatory' => true,
        ],
        [
            'schedule_key' => 'B',
            'unit' => 'Algebra',
            'option_code' => 'T2',
            'class_type' => 'TP',
            'day' => 'Terca',
            'day_index' => 2,
            'start' => '08:00',
            'end' => '10:00',
            'mandatory' => false,
        ],
    ]);

    expect($result['schedules'])->toHaveCount(2);
    expect($result['schedules'][0]['key'])->toBe('B');
    expect($result['schedules'][0]['metrics']['daysCount'])->toBe(1);
    expect($result['schedules'][1]['key'])->toBe('A');
    expect($result['schedules'][1]['metrics']['gaps'])->toBe(120);

    expect($result['units'])->toHaveCount(2);

    $algebra = collect($result['units'])->firstWhere('name', 'Algebra');
    $fisica = collect($result['units'])->firstWhere('name', 'Fisica');

    expect($algebra)->not->toBeNull();
    expect($algebra['options'])->toHaveCount(2);

    expect($fisica)->not->toBeNull();
    expect($fisica['mandatoryEvents'])->toHaveCount(1);
});
