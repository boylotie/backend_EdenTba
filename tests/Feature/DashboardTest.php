<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('non-admin users are redirected away from the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('home'));
});

test('admin users can visit the dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
