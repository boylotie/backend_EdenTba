<?php

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Models\UserDevice;
use App\Modules\Notifications\Services\AdminBroadcastService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

beforeEach(function (): void {
    Queue::fake();
    Http::fake();
});

function programReminderYear(): Year
{
    return Year::create(['label' => '2026-2027', 'is_current' => true]);
}

function programReminderWeek(Year $year): Week
{
    return Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
}

function programReminderDevice(User $user, int $suffix): UserDevice
{
    return UserDevice::create([
        'user_id' => $user->id,
        'token' => "expo-token-reminder-{$suffix}",
        'provider' => 'expo',
    ]);
}

it('envoie le rappel à l heure fixe pour le programme N jours avant', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 08:15:00'));

    User::factory()->create();
    $week = programReminderWeek(programReminderYear());
    $target = now()->startOfDay()->addDays(3);
    $program = Program::create([
        'week_id' => $week->id,
        'day_of_week' => $target->dayOfWeekIso,
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'type' => 'Culte',
    ]);

    $this->artisan('reminders:send-programs')->assertSuccessful();

    $this->assertDatabaseHas('user_notifications', [
        'type' => NotificationService::TYPE_PROGRAM_REMINDER,
        'title' => 'Rappel : Culte',
    ]);

    $this->assertDatabaseHas('program_reminders', [
        'program_id' => $program->id,
        'occurrence_date' => $target->toDateString(),
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'reminders.program_sent',
        'entity_type' => 'program',
        'entity_id' => (string) $program->id,
    ]);
});

it('ne fait rien hors de l heure fixe', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 10:15:00'));

    User::factory()->create();
    $week = programReminderWeek(programReminderYear());
    $target = now()->startOfDay()->addDays(3);
    Program::create([
        'week_id' => $week->id,
        'day_of_week' => $target->dayOfWeekIso,
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'type' => 'Culte',
    ]);

    $this->artisan('reminders:send-programs')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('ignore les programmes des autres jours', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 08:15:00'));

    User::factory()->create();
    $week = programReminderWeek(programReminderYear());
    $target = now()->startOfDay()->addDays(3);
    $other = $target->dayOfWeekIso % 7 + 1;

    Program::create([
        'week_id' => $week->id,
        'day_of_week' => $other,
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'type' => 'Culte',
    ]);

    $this->artisan('reminders:send-programs')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('n envoie qu une fois par occurrence', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 08:15:00'));

    User::factory()->create();
    $week = programReminderWeek(programReminderYear());
    $target = now()->startOfDay()->addDays(3);
    Program::create([
        'week_id' => $week->id,
        'day_of_week' => $target->dayOfWeekIso,
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'type' => 'Culte',
    ]);

    $this->artisan('reminders:send-programs')->assertSuccessful();
    $this->artisan('reminders:send-programs')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 1);
    $this->assertDatabaseCount('program_reminders', 1);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('reprend un rappel en échec sans interrompre les autres', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 08:15:00'));

    User::factory()->create();
    $week = programReminderWeek(programReminderYear());
    $target = now()->startOfDay()->addDays(3);
    $failing = Program::create([
        'week_id' => $week->id,
        'day_of_week' => $target->dayOfWeekIso,
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'type' => 'En échec',
    ]);
    $healthy = Program::create([
        'week_id' => $week->id,
        'day_of_week' => $target->dayOfWeekIso,
        'start_time' => '11:00',
        'duration_minutes' => 60,
        'type' => 'Ok',
    ]);

    $calls = 0;
    $mock = Mockery::mock(AdminBroadcastService::class);
    $mock->shouldReceive('broadcast')
        ->with('Rappel : En échec', Mockery::type('string'), NotificationService::TYPE_PROGRAM_REMINDER)
        ->andReturnUsing(function () use (&$calls): int {
            $calls++;
            if ($calls === 1) {
                throw new RuntimeException('Échec simulé.');
            }

            return 1;
        });
    $mock->shouldReceive('broadcast')
        ->with('Rappel : Ok', Mockery::type('string'), NotificationService::TYPE_PROGRAM_REMINDER)
        ->andReturn(1);
    $this->app->instance(AdminBroadcastService::class, $mock);

    $this->artisan('reminders:send-programs')->assertSuccessful();

    $this->assertDatabaseMissing('program_reminders', ['program_id' => $failing->id]);
    $this->assertDatabaseHas('program_reminders', ['program_id' => $healthy->id]);

    $this->artisan('reminders:send-programs')->assertSuccessful();

    $this->assertDatabaseHas('program_reminders', ['program_id' => $failing->id]);
});

it('ne fait rien quand les rappels sont désactivés', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 08:15:00'));

    app(SettingsService::class)->replace(['rappel_actif' => false]);

    User::factory()->create();
    $week = programReminderWeek(programReminderYear());
    $target = now()->startOfDay()->addDays(3);
    Program::create([
        'week_id' => $week->id,
        'day_of_week' => $target->dayOfWeekIso,
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'type' => 'Culte',
    ]);

    $this->artisan('reminders:send-programs')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('planifie un push avec les tokens des utilisateurs actifs', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 08:15:00'));

    $active = User::factory()->create();
    programReminderDevice($active, 1);

    $week = programReminderWeek(programReminderYear());
    $target = now()->startOfDay()->addDays(3);
    Program::create([
        'week_id' => $week->id,
        'day_of_week' => $target->dayOfWeekIso,
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'type' => 'Culte',
    ]);

    $this->artisan('reminders:send-programs')->assertSuccessful();

    Queue::assertPushed(SendPushNotifications::class, fn (SendPushNotifications $job): bool => in_array('expo-token-reminder-1', $job->tokens, true));
});

it('enregistre la planification toutes les minutes', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('reminders:send-programs')
        ->assertSuccessful();
});
