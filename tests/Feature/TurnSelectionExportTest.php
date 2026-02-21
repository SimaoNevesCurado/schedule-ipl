<?php

declare(strict_types=1);

it('renders turn selection export page', function (): void {
    $response = $this->get(route('turn-selection-export.index'));

    $response->assertSuccessful();
    $response->assertSee('Exportar horários por texto de turnos');
    $response->assertSee('Sem upload manual');
    $response->assertSee('Podes pedir ao ChatGPT');
});

it('validates that day and time are included', function (): void {
    $response = $this->from(route('turn-selection-export.index'))
        ->post(route('turn-selection-export.apply'), [
            'selection_text' => 'API T1 API PL2 ES TP1',
        ]);

    $response->assertRedirect(route('turn-selection-export.index'));
    $response->assertSessionHasErrors(['selection_text']);
});

it('processes text and redirects to schedule builder', function (): void {
    $response = $this->post(route('turn-selection-export.apply'), [
        'selection_text' => implode("\n", [
            'Seg 08:00-10:00 API T1',
            'Seg 15:00-18:00 ES PL2',
            'Ter 11:00-14:00 IA PL2',
            'Ter 16:00-18:00 ES TP1',
            'Qua 11:00-14:00 SI PL2',
            'Qui 09:00-11:00 API PL2',
            'Qui 10:00-13:00 SBD PL2',
            'Sex 17:00-19:00 SBD OBR',
        ]),
    ]);

    $response->assertRedirect(route('schedules.index'));
    $response->assertSessionHas('schedules.text.rows');
    $response->assertSessionHas('schedules.text.source', 'texto');
});

it('accepts day names in full portuguese', function (): void {
    $response = $this->post(route('turn-selection-export.apply'), [
        'selection_text' => implode("\n", [
            'Segunda 08:00-10:00 API T1',
            'Terça 16:00-18:00 ES TP1',
            'Quinta 09:00-11:00 API PL2',
        ]),
    ]);

    $response->assertRedirect(route('schedules.index'));
    $response->assertSessionHas('schedules.text.rows');
});

it('processes schedules with arbitrary unit acronyms', function (): void {
    $response = $this->post(route('turn-selection-export.apply'), [
        'selection_text' => implode("\n", [
            'Qua 10:00-11:00 ALNET T2',
            'Ter 09:30-12:30 ALNET PL1',
            'Qui 15:00-18:00 ALNET PL7',
            'Seg 08:00-10:00 API T1',
        ]),
    ]);

    $response->assertRedirect(route('schedules.index'));
    $response->assertSessionHas('schedules.text.rows');
    $response->assertSessionHas('schedules.text.source', 'texto');
});
