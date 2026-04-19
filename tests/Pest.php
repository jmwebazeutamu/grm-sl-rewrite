<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function asSuperAdmin(): \App\Domain\Identity\Models\User
{
    $user = \App\Domain\Identity\Models\User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}
