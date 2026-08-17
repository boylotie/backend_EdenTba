<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Models\Content;
use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\YearService;
use Database\Seeders\RbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
    Storage::fake('content_images');
    Queue::fake();
});

function contentsPageAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function contentsPageRecord(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/test.mp3', 'audio');

    return Content::create(array_merge([
        'title' => 'Enseignement',
        'file_path' => 'contents/test.mp3',
        'original_filename' => 'test.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

it('affiche l écran des contenus pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    contentsPageRecord();

    $this->actingAs($user)
        ->get('/admin/contents')
        ->assertOk()
        ->assertSee('Contenus audio')
        ->assertSee('Enseignement');
});

it('refuse l accès sans permission content.view', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $role->permissions()->detach(Permission::where('name', 'content.view')->firstOrFail()->id);

    $this->actingAs($user)->get('/admin/contents')->assertForbidden();
});

it('affiche les mois avec leurs noms et l option semaine repliable', function () {
    $year = app(YearService::class)->create(['label' => '2026-2027']);
    $year->update(['is_current' => true]);

    $this->actingAs(contentsPageAdmin())
        ->get('/admin/contents')
        ->assertOk()
        ->assertSee('Janvier')
        ->assertSee('Décembre')
        ->assertSee('Associer à une semaine');
});

it('crée un contenu via le formulaire', function () {
    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('audioFile', UploadedFile::fake()->create('predication.mp3', 100))
        ->set('createTitle', 'Enseignement du soir')
        ->set('createSpeaker', 'Pasteur Jean')
        ->call('createContent')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('contents', [
        'title' => 'Enseignement du soir',
        'speaker' => 'Pasteur Jean',
        'status' => Content::STATUS_DRAFT,
    ]);
});

it('crée un contenu avec jour, notes et compte-rendu d approbation', function () {
    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('audioFile', UploadedFile::fake()->create('predication.mp3', 100))
        ->set('createTitle', 'Prédication du dimanche')
        ->set('createDayOfWeek', 7)
        ->set('createNotes', "Plan : la foi, l'espérance.")
        ->set('createApprovedBy', 'Pasteur Jean')
        ->set('createApprovalComment', 'Compte-rendu validé.')
        ->call('createContent')
        ->assertHasNoErrors();

    $content = Content::query()->where('title', 'Prédication du dimanche')->firstOrFail();

    expect($content->day_of_week)->toBe(7)
        ->and($content->notes)->toBe("Plan : la foi, l'espérance.")
        ->and($content->approved_by)->toBe('Pasteur Jean')
        ->and($content->approval_comment)->toBe('Compte-rendu validé.')
        ->and($content->approved_at)->not->toBeNull();
});

it('rejette un jour de semaine hors plage dans le formulaire', function () {
    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('audioFile', UploadedFile::fake()->create('predication.mp3', 100))
        ->set('createTitle', 'Contenu')
        ->set('createDayOfWeek', 9)
        ->call('createContent')
        ->assertHasErrors('createDayOfWeek');

    $this->assertDatabaseCount('contents', 0);
});

it('valide le titre et le fichier audio requis', function () {
    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->call('createContent')
        ->assertHasErrors(['createTitle', 'audioFile']);
});

it('rejette un mois hors de l année sélectionnée', function () {
    $year = Year::create(['label' => '2026-2027']);
    $otherYear = Year::create(['label' => '2025-2026']);
    $month = Month::create(['year_id' => $otherYear->id, 'month_number' => 3]);

    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('audioFile', UploadedFile::fake()->create('predication.mp3', 100))
        ->set('createTitle', 'Contenu')
        ->set('createYearId', $year->id)
        ->set('createMonthId', $month->id)
        ->call('createContent')
        ->assertHasErrors('createMonthId');

    $this->assertDatabaseCount('contents', 0);
});

it('met à jour les métadonnées via le formulaire', function () {
    $content = contentsPageRecord();

    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('editId', $content->id)
        ->set('editTitle', 'Nouveau titre')
        ->set('editSpeaker', 'Frère Marc')
        ->call('updateContent')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('contents', ['id' => $content->id, 'title' => 'Nouveau titre', 'speaker' => 'Frère Marc']);
});

it('met à jour le jour, les notes et l approbation via le formulaire', function () {
    $content = contentsPageRecord();

    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('editId', $content->id)
        ->set('editTitle', 'Enseignement du jeudi')
        ->set('editDayOfWeek', 4)
        ->set('editNotes', 'Notes du jeudi.')
        ->set('editApprovedBy', 'Frère Marc')
        ->call('updateContent')
        ->assertHasNoErrors();

    $content->refresh();

    expect($content->day_of_week)->toBe(4)
        ->and($content->notes)->toBe('Notes du jeudi.')
        ->and($content->approved_by)->toBe('Frère Marc')
        ->and($content->approved_at)->not->toBeNull();
});

it('publie un brouillon via la transition', function () {
    $content = contentsPageRecord(['status' => Content::STATUS_DRAFT]);

    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->call('changeStatus', $content->id, Content::STATUS_PUBLISHED);

    $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => Content::STATUS_PUBLISHED]);
});

it('programme une publication avec une date future', function () {
    $content = contentsPageRecord(['status' => Content::STATUS_DRAFT]);

    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('scheduleTarget', $content->id)
        ->set('scheduleAt', now()->addDay()->format('Y-m-d H:i'))
        ->call('confirmSchedule')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => Content::STATUS_SCHEDULED]);
});

it('rejette une transition interdite', function () {
    $content = contentsPageRecord(['status' => Content::STATUS_PUBLISHED]);

    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->call('changeStatus', $content->id, Content::STATUS_DRAFT);

    $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => Content::STATUS_PUBLISHED]);
});

it('supprime un contenu via le formulaire', function () {
    $content = contentsPageRecord();

    Livewire::actingAs(contentsPageAdmin())
        ->test('pages::admin.contents')
        ->set('deleteTarget', $content->id)
        ->call('deleteContent');

    $this->assertDatabaseMissing('contents', ['id' => $content->id]);
});
