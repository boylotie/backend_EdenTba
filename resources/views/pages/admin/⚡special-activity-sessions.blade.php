<?php

use App\Modules\SpecialActivities\Models\Session;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Modules\SpecialActivities\Services\SpecialActivityService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sessions de l\'activité')] class extends Component {
    public SpecialActivity $activity;

    /**
     * @var list<array{id: int, day_of_week: int, day_label: string, start_time: string, duration_minutes: int, place: string|null, theme: string|null}>
     */
    public array $sessions = [];

    public int $dayOfWeek = 1;

    public string $startTime = '';

    public int $durationMinutes = 60;

    public string $place = '';

    public string $theme = '';

    public ?int $editId = null;

    public int $editDayOfWeek = 1;

    public string $editStartTime = '';

    public int $editDurationMinutes = 60;

    public string $editPlace = '';

    public string $editTheme = '';

    public ?int $deleteTarget = null;

    /**
     * @return array<int, string>
     */
    public function getDayLabelsProperty(): array
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
        ];
    }

    public function mount(SpecialActivity $activity): void
    {
        $this->activity = $activity;
        $this->refresh();
    }

    public function addSession(): void
    {
        $this->validate([
            'dayOfWeek' => ['required', 'integer', 'min:1', 'max:7'],
            'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'place' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(SpecialActivityService::class)->addSession($this->activity, [
                'day_of_week' => $this->dayOfWeek,
                'start_time' => $this->startTime,
                'duration_minutes' => $this->durationMinutes,
                'place' => $this->place !== '' ? $this->place : null,
                'theme' => $this->theme !== '' ? $this->theme : null,
            ]);
        } catch (DomainException $e) {
            $this->addError('startTime', $e->getMessage());

            return;
        }

        $this->reset('startTime', 'place', 'theme');
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Session ajoutée.'));
    }

    public function startEdit(int $id): void
    {
        $session = Session::findOrFail($id);

        $this->editId = $session->id;
        $this->editDayOfWeek = $session->day_of_week;
        $this->editStartTime = substr((string) $session->start_time, 0, 5);
        $this->editDurationMinutes = $session->duration_minutes;
        $this->editPlace = $session->place ?? '';
        $this->editTheme = $session->theme ?? '';

        $this->modal('edit-session')->show();
    }

    public function updateSession(): void
    {
        $session = Session::findOrFail($this->editId);

        $this->validate([
            'editDayOfWeek' => ['required', 'integer', 'min:1', 'max:7'],
            'editStartTime' => ['required', 'date_format:H:i'],
            'editDurationMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'editPlace' => ['nullable', 'string', 'max:255'],
            'editTheme' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(SpecialActivityService::class)->updateSession($session, [
                'day_of_week' => $this->editDayOfWeek,
                'start_time' => $this->editStartTime,
                'duration_minutes' => $this->editDurationMinutes,
                'place' => $this->editPlace !== '' ? $this->editPlace : null,
                'theme' => $this->editTheme !== '' ? $this->editTheme : null,
            ]);
        } catch (DomainException $e) {
            $this->addError('editStartTime', $e->getMessage());

            return;
        }

        $this->editId = null;
        $this->modal('edit-session')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Session mise à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-session')->show();
    }

    public function deleteSession(): void
    {
        app(SpecialActivityService::class)->deleteSession(Session::findOrFail($this->deleteTarget));

        $this->deleteTarget = null;
        $this->refresh();

        $this->modal('delete-session')->close();

        Flux::toast(variant: 'success', text: __('Session supprimée.'));
    }

    private function refresh(): void
    {
        $this->sessions = $this->activity->sessions()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Session $session): array => [
                'id' => $session->id,
                'day_of_week' => $session->day_of_week,
                'day_label' => $this->day_labels[$session->day_of_week],
                'start_time' => substr($session->start_time, 0, 5),
                'duration_minutes' => $session->duration_minutes,
                'place' => $session->place,
                'theme' => $session->theme,
            ])
            ->all();
    }
}; ?>

<section class="w-full">
    <div class="flex items-center gap-4">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.special-activities.index')" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <div>
            <flux:heading size="xl" level="1">{{ $activity->name }}</flux:heading>
            <flux:text class="mt-1">
                {{ $activity->week->label }}
                · {{ $activity->activityType->label }}
                · {{ $activity->mode === 'replace' ? __('Remplacement') : __('Complément') }}
            </flux:text>
        </div>
    </div>

    <form wire:submit="addSession" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouvelle session') }}</flux:heading>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-5">
                <flux:field>
                    <flux:label>{{ __('Jour') }}</flux:label>
                    <flux:select wire:model="dayOfWeek">
                        @foreach ($this->day_labels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="dayOfWeek" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Début') }}</flux:label>
                    <flux:input type="time" wire:model="startTime" />
                    <flux:error name="startTime" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Durée (min)') }}</flux:label>
                    <flux:input type="number" wire:model="durationMinutes" />
                    <flux:error name="durationMinutes" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Lieu') }}</flux:label>
                    <flux:input wire:model="place" />
                    <flux:error name="place" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Thème') }}</flux:label>
                    <flux:input wire:model="theme" />
                    <flux:error name="theme" />
                </flux:field>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <flux:button variant="primary" type="submit">{{ __('Ajouter') }}</flux:button>
            </div>
        </flux:card>
    </form>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Jour') }}</flux:table.column>
                <flux:table.column>{{ __('Début') }}</flux:table.column>
                <flux:table.column>{{ __('Durée') }}</flux:table.column>
                <flux:table.column>{{ __('Lieu') }}</flux:table.column>
                <flux:table.column>{{ __('Thème') }}</flux:table.column>
                <flux:table.column class="w-28" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($sessions as $session)
                    <flux:table.row>
                        <flux:table.cell>{{ $session['day_label'] }}</flux:table.cell>
                        <flux:table.cell>{{ $session['start_time'] }}</flux:table.cell>
                        <flux:table.cell>{{ $session['duration_minutes'] }} min</flux:table.cell>
                        <flux:table.cell>{{ $session['place'] }}</flux:table.cell>
                        <flux:table.cell>{{ $session['theme'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $session['id'] }})">
                                {{ __('Modifier') }}
                            </flux:button>

                            <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $session['id'] }})">
                                {{ __('Supprimer') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400">
                            {{ __('Aucune session pour cette activité.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-session" :dismissible="false">
        <form wire:submit="updateSession">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier la session') }}</flux:heading>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <flux:field>
                    <flux:label>{{ __('Jour') }}</flux:label>
                        <flux:select wire:model="editDayOfWeek">
                            @foreach ($this->day_labels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editDayOfWeek" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Début') }}</flux:label>
                        <flux:input type="time" wire:model="editStartTime" />
                        <flux:error name="editStartTime" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Durée (min)') }}</flux:label>
                        <flux:input type="number" wire:model="editDurationMinutes" min="1" max="1440" />
                        <flux:error name="editDurationMinutes" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Lieu') }}</flux:label>
                        <flux:input wire:model="editPlace" />
                        <flux:error name="editPlace" />
                    </flux:field>

                    <flux:field class="lg:col-span-2">
                    <flux:label>{{ __('Thème') }}</flux:label>
                        <flux:input wire:model="editTheme" />
                        <flux:error name="editTheme" />
                    </flux:field>
                </div>

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button variant="ghost" type="button" wire:click="$set('editId', null)">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Enregistrer') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-session" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer cette session ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteSession">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
