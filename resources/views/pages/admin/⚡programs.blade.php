<?php

use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\ProgramService;
use App\Modules\Organization\Support\ProgramSchedule;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Programmes')] class extends Component {
    public Year $year;

    public Week $week;

    /**
     * @var list<array{id: int, day_of_week: int, day_label: string, start_time: string, duration_minutes: int, type: string}>
     */
    public array $programs = [];

    public int $dayOfWeek = 1;

    public string $startTime = '';

    public int $durationMinutes = 60;

    public string $type = '';

    public ?int $editId = null;

    public int $editDayOfWeek = 1;

    public string $editStartTime = '';

    public int $editDurationMinutes = 60;

    public string $editType = '';

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

    public function mount(Year $year, Week $week): void
    {
        $this->year = $year;
        $this->week = $week;
        $this->refresh();
    }

    public function createProgram(): void
    {
        $this->validate([
            'dayOfWeek' => ['required', 'integer', 'min:1', 'max:7'],
            'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'type' => ['required', 'string', 'max:255'],
        ]);

        if (ProgramSchedule::hasOverlap($this->week, null, $this->dayOfWeek, $this->startTime, $this->durationMinutes)) {
            $this->addError('startTime', 'Ce programme chevauche un autre programme de la même journée.');

            return;
        }

        app(ProgramService::class)->create($this->week, [
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'duration_minutes' => $this->durationMinutes,
            'type' => trim($this->type),
        ]);

        $this->reset('startTime', 'type');
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Programme créé.'));
    }

    public function startEdit(int $id): void
    {
        $program = Program::findOrFail($id);

        $this->editId = $program->id;
        $this->editDayOfWeek = $program->day_of_week;
        $this->editStartTime = $program->start_time;
        $this->editDurationMinutes = $program->duration_minutes;
        $this->editType = $program->type;

        $this->modal('edit-program')->show();
    }

    public function updateProgram(): void
    {
        $program = Program::findOrFail($this->editId);

        $this->validate([
            'editDayOfWeek' => ['required', 'integer', 'min:1', 'max:7'],
            'editStartTime' => ['required', 'date_format:H:i'],
            'editDurationMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'editType' => ['required', 'string', 'max:255'],
        ]);

        if (ProgramSchedule::hasOverlap($this->week, $program->id, $this->editDayOfWeek, $this->editStartTime, $this->editDurationMinutes)) {
            $this->addError('editStartTime', 'Ce programme chevauche un autre programme de la même journée.');

            return;
        }

        app(ProgramService::class)->update($program, [
            'day_of_week' => $this->editDayOfWeek,
            'start_time' => $this->editStartTime,
            'duration_minutes' => $this->editDurationMinutes,
            'type' => trim($this->editType),
        ]);

        $this->editId = null;
        $this->modal('edit-program')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Programme mis à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-program')->show();
    }

    public function deleteProgram(): void
    {
        $program = Program::findOrFail($this->deleteTarget);

        if (! app(ProgramService::class)->delete($program)) {
            Flux::toast(variant: 'danger', text: __('Ce programme est encore utilisé.'));

            return;
        }

        $this->deleteTarget = null;
        $this->refresh();

        $this->modal('delete-program')->close();

        Flux::toast(variant: 'success', text: __('Programme supprimé.'));
    }

    private function refresh(): void
    {
        $this->programs = $this->week->programs()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Program $program): array => [
                'id' => $program->id,
                'day_of_week' => $program->day_of_week,
                'day_label' => $this->day_labels[$program->day_of_week],
                'start_time' => $program->start_time,
                'duration_minutes' => $program->duration_minutes,
                'type' => $program->type,
            ])
            ->all();
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Programmes') }}</flux:heading>
            <flux:text class="mt-1">{{ $this->year->label }} — {{ $this->week->label }}</flux:text>
        </div>

        <flux:button variant="ghost" :href="route('admin.years.weeks.index', $this->year->id)" wire:navigate>
            {{ __('Retour aux semaines') }}
        </flux:button>
    </div>

    <form wire:submit="createProgram" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouveau programme') }}</flux:heading>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
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
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:input wire:model="type" placeholder="{{ __('Culte') }}" />
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Heure de début') }}</flux:label>
                    <flux:input type="time" wire:model="startTime" />
                    <flux:error name="startTime" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Durée (minutes)') }}</flux:label>
                    <flux:input type="number" wire:model="durationMinutes" min="1" max="1440" />
                    <flux:error name="durationMinutes" />
                </flux:field>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <flux:button variant="primary" type="submit">{{ __('Créer') }}</flux:button>
            </div>
        </flux:card>
    </form>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Jour') }}</flux:table.column>
                <flux:table.column>{{ __('Heure') }}</flux:table.column>
                <flux:table.column>{{ __('Durée') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column class="w-40" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($programs as $program)
                    <flux:table.row>
                        <flux:table.cell>{{ $program['day_label'] }}</flux:table.cell>
                        <flux:table.cell>{{ $program['start_time'] }}</flux:table.cell>
                        <flux:table.cell>{{ $program['duration_minutes'] }} {{ __('min') }}</flux:table.cell>
                        <flux:table.cell>{{ $program['type'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $program['id'] }})">
                                {{ __('Modifier') }}
                            </flux:button>

                            <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $program['id'] }})">
                                {{ __('Supprimer') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('Aucun programme créé pour cette semaine.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-program" :dismissible="false">
        <form wire:submit="updateProgram">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier le programme') }}</flux:heading>
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
                    <flux:label>{{ __('Type') }}</flux:label>
                        <flux:input wire:model="editType" />
                        <flux:error name="editType" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Heure de début') }}</flux:label>
                        <flux:input type="time" wire:model="editStartTime" />
                        <flux:error name="editStartTime" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Durée (minutes)') }}</flux:label>
                        <flux:input type="number" wire:model="editDurationMinutes" min="1" max="1440" />
                        <flux:error name="editDurationMinutes" />
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

    <flux:modal name="delete-program" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer ce programme ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteProgram">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
