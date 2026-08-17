<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Speakers\Models\Speaker;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function speakersPageAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

it('affiche l écran des conférenciers pour un administrateur', function () {
    Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    $this->actingAs(speakersPageAdmin())
        ->get('/admin/speakers')
        ->assertOk()
        ->assertSee('Conférenciers')
        ->assertSee('Pasteur Jean');
});

it('crée un conférencier via le formulaire', function () {
    Livewire::actingAs(speakersPageAdmin())
        ->test('pages::admin.speakers')
        ->set('name', 'Pasteur Paul')
        ->set('title', 'pasteur')
        ->call('createSpeaker')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('speakers', ['name' => 'Pasteur Paul', 'title' => 'pasteur']);
});

it('valide les champs requis lors de la création', function () {
    Livewire::actingAs(speakersPageAdmin())
        ->test('pages::admin.speakers')
        ->set('name', '')
        ->call('createSpeaker')
        ->assertHasErrors(['name']);
});

it('modifie un conférencier via le formulaire', function () {
    $speaker = Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    Livewire::actingAs(speakersPageAdmin())
        ->test('pages::admin.speakers')
        ->call('startEdit', $speaker->id)
        ->set('editName', 'Pasteur Jean-Marc')
        ->set('editTitle', 'pasteur')
        ->call('updateSpeaker')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('speakers', ['id' => $speaker->id, 'name' => 'Pasteur Jean-Marc']);
});

it('supprime un conférencier non utilisé', function () {
    $speaker = Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    Livewire::actingAs(speakersPageAdmin())
        ->test('pages::admin.speakers')
        ->set('deleteTarget', $speaker->id)
        ->call('deleteSpeaker');

    $this->assertDatabaseMissing('speakers', ['id' => $speaker->id]);
});

it('refuse l accès sans permission speaker.view', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $role->permissions()->detach(Permission::where('name', 'speaker.view')->firstOrFail()->id);

    $this->actingAs($user)
        ->get('/admin/speakers')
        ->assertForbidden();
});
