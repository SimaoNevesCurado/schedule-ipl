<?php

declare(strict_types=1);

it('renders text input page as home', function (): void {
    $response = $this->get(route('home'));

    $response->assertSuccessful();
    $response->assertSee('Exportar horários por texto de turnos');
});
