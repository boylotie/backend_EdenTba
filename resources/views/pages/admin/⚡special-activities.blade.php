<?php

use App\Modules\Organization\Models\Week;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Modules\SpecialActivities\Services\SpecialActivityService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Activités spéciales')] class extends Component {
    /**
     * @var list<array{id: int, name: string, mode: string, week_label: string, type_label: string, sessions_count: int}>
     */
    public array $activities = [];

    /**
     * @var list<array{id: int, label: string}>
     */
    public array $weeks = [];

    /**
     * @var list<array{id: int, label: string}>
     */
    public array $types = [];

    public int $weekId = 0;

    public int $activityTypeId = 0;

    public string $name = '';

    public string $mode = 'complement';

    public string $startsOn = '';

    public string $endsOn = '';

    public ?int $editId = null;

    public int $editWeekId = 0;

    public int $editActivityTypeId = 0;

    public string $editName = '';

    public string $editMode = 'complement';

    public string $editStartsOn = '';

    public string $editEndsOn = '';

    public ?int $deleteTarget = null;

    public function mount(): void
    {
        $this->weeks = Week::query()
            ->with('year')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Week $week): array => [
                'id' => $week->id,
                'label' => "{$week->year->label} — {$week->label}",
            ])
            ->all();

        $this->types = ActivityType::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get()
            ->map(fn (ActivityType $type): array => ['id' => $type->id, 'label' => $type->label])
            ->all();

        $this->refresh();
    }

    public function createActivity(): void
    {
        $this->validate([
            'weekId' => ['required', 'integer', 'exists:weeks,id'],
            'activityTypeId' => ['required', 'integer', 'exists:activity_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'string', 'in:replace,complement'],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
        ]);

        app(SpecialActivityService::class)->create([
            'week_id' => $this->weekId,
            'activity_type_id' => $this->activityTypeId,
            'name' => trim($this->name),
            'mode' => $this->mode,
            'starts_on' => $this->startsOn !== '' ? $this->startsOn : null,
            'ends_on' => $this->endsOn !== '' ? $this->endsOn : null,
        ]);

        $this->reset('weekId', 'activityTypeId', 'name', 'mode', 'startsOn', 'endsOn');
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Activité créée.'));
    }

    public function startEdit(int $id): void
    {
        $activity = SpecialActivity::findOrFail($id);

        $this->editId = $activity->id;
        $this->editWeekId = $activity->week_id;
        $this->editActivityTypeId = $activity->activity_type_id;
        $this->editName = $activity->name;
        $this->editMode = $activity->mode;
        $this->editStartsOn = $activity->starts_on?->toDateString() ?? '';
        $this->editEndsOn = $activity->ends_on?->toDateString() ?? '';

        $this->modal('edit-activity')->show();
    }

    public function updateActivity(): void
    {
        $activity = SpecialActivity::findOrFail($this->editId);

        $this->validate([
            'editWeekId' => ['required', 'integer', 'exists:weeks,id'],
            'editActivityTypeId' => ['required', 'integer', 'exists:activity_types,id'],
            'editName' => ['required', 'string', 'max:255'],
            'editMode' => ['required', 'string', 'in:replace,complement'],
            'editStartsOn' => ['nullable', 'date'],
            'editEndsOn' => ['nullable', 'date', 'after_or_equal:editStartsOn'],
        ]);

        app(SpecialActivityService::class)->update($activity, [
            'week_id' => $this->editWeekId,
            'activity_type_id' => $this->editActivityTypeId,
            'name' => trim($this->editName),
            'mode' => $this->editMode,
            'starts_on' => $this->editStartsOn !== '' ? $this->editStartsOn : null,
            'ends_on' => $this->editEndsOn !== '' ? $this->editEndsOn : null,
        ]);

        $this->editId = null;
        $this->modal('edit-activity')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Activité mise à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-activity')->show();
    }

    public function deleteActivity(): void
    {
        $activity = SpecialActivity::findOrFail($this->deleteTarget);

        if (! app(SpecialActivityService::class)->delete($activity)) {
            Flux::toast(variant: 'danger', text: __('Cette activité est encore utilisée.'));

            return;
        }

        $this->deleteTarget = null;
        $this->refresh();

        $this->modal('delete-activity')->close();

        Flux::toast(variant: 'success', text: __('Activité supprimée.'));
    }

    private function refresh(): void
    {
        $this->activities = SpecialActivity::query()
            ->with(['week', 'activityType'])
            ->withCount('sessions')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SpecialActivity $activity): array => [
                'id' => $activity->id,
                'name' => $activity->name,
                'mode' => $activity->mode,
                'week_label' => $activity->week->label,
                'type_label' => $activity->activityType->label,
                'sessions_count' => $activity->sessions_count,
            ])
            ->all();
    }
}; ?>

<section class="w-full">
    <div>
        <flux:heading size="xl" level="1">{{ __('Activités spéciales') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Activités multi-jours rattachées à une semaine.') }}</flux:text>
    </div>

    <form wire:submit="createActivity" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouvelle activité') }}</flux:heading>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Semaine') }}</flux:label>
                    <flux:select wire:model="weekId">
                        <option value="0">—</option>
                        @foreach ($this->weeks as $week)
                            <option value="{{ $week['id'] }}">{{ $week['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="weekId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Type d\'activité') }}</flux:label>
                    <flux:select wire:model="activityTypeId">
                        <option value="0">—</option>
                        @foreach ($this->types as $type)
                            <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="activityTypeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Mode') }}</flux:label>
                    <flux:select wire:model="mode">
                        <option value="complement">{{ __('Complément') }}</option>
                        <option value="replace">{{ __('Remplacement') }}</option>
                    </flux:select>
                    <flux:error name="mode" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Début') }}</flux:label>
                    <flux:input type="date" wire:model="startsOn" />
                    <flux:error name="startsOn" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fin') }}</flux:label>
                    <flux:input type="date" wire:model="endsOn" />
                    <flux:error name="endsOn" />
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
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Semaine') }}</flux:table.column>
                <flux:table.column>{{ __('Mode') }}</flux:table.column>
                <flux:table.column>{{ __('Sessions') }}</flux:table.column>
                <flux:table.column class="w-56" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($activities as $activity)
                    <flux:table.row>
                        <flux:table.cell>{{ $activity['name'] }}</flux:table.cell>
                        <flux:table.cell>{{ $activity['type_label'] }}</flux:table.cell>
                        <flux:table.cell>{{ $activity['week_label'] }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $activity['mode'] === 'replace' ? __('Remplacement') : __('Complément') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $activity['sessions_count'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" :href="route('admin.special-activities.show', $activity['id'])" wire:navigate>
                                {{ __('Sessions') }}
                            </flux:button>

                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $activity['id'] }})">
                                {{ __('Modifier') }}
                            </flux:button>

                            <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $activity['id'] }})">
                                {{ __('Supprimer') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400">
                            {{ __('Aucune activité spéciale.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-activity" :dismissible="false">
        <form wire:submit="updateActivity">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier l\'activité') }}</flux:heading>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <flux:field>
                    <flux:label>{{ __('Semaine') }}</flux:label>
                        <flux:select wire:model="editWeekId">
                            @foreach ($this->weeks as $week)
                                <option value="{{ $week['id'] }}">{{ $week['label'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editWeekId" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Type d\'activité') }}</flux:label>
                        <flux:select wire:model="editActivityTypeId">
                            @foreach ($this->types as $type)
                                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editActivityTypeId" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                        <flux:input wire:model="editName" />
                        <flux:error name="editName" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Mode') }}</flux:label>
                        <flux:select wire:model="editMode">
                            <option value="complement">{{ __('Complément') }}</option>
                            <option value="replace">{{ __('Remplacement') }}</option>
                        </flux:select>
                        <flux:error name="editMode" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Début') }}</flux:label>
                        <flux:input type="date" wire:model="editStartsOn" />
                        <flux:error name="editStartsOn" />
                    </flux:field>

                    <flux:field>
                    <flux:label>{{ __('Fin') }}</flux:label>
                        <flux:input type="date" wire:model="editEndsOn" />
                        <flux:error name="editEndsOn" />
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

    <flux:modal name="delete-activity" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer cette activité ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteActivity">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
