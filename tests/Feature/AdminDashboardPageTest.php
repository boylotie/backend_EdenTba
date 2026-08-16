<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Analytics\Models\ListeningEvent;
use App\Modules\Content\Models\Content;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('redirige un utilisateur sans rôle admin hors du tableau de bord', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertRedirect(route('home'));
});

it('affiche le tableau de bord avec statistiques', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $content = Content::query()->create([
        'title' => 'Sermon de rentrée',
        'status' => Content::STATUS_PUBLISHED,
        'file_path' => 'audio/sermon.mp3',
        'original_filename' => 'sermon.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 1024,
    ]);

    ListeningEvent::create(['content_id' => $content->id, 'event_type' => 'play', 'position_seconds' => 10, 'occurred_at' => now()->subDay()]);
    ListeningEvent::create(['content_id' => $content->id, 'event_type' => 'play', 'position_seconds' => 10, 'occurred_at' => now()->subDay()]);
    ListeningEvent::create(['content_id' => $content->id, 'event_type' => 'completed', 'position_seconds' => 100, 'occurred_at' => now()->subDay()]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Sermon de rentrée')
        ->assertSee('2')
        ->assertSee('1');
});

it('affiche l état vide sans données', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Aucune donnée pour cette période.');
});
