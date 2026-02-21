<?php

declare(strict_types=1);

it('has welcome page', function (): void {
    $page = visit('/');

    $page->assertSee('Starter Kit Vue (No Auth)');
});
