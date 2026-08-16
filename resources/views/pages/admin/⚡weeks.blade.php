<?php

use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\WeekService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Semaines')] class extends Component {
    public Year $year;

    /**
     * @var list<array{id: int, label: string}>
     */
    public array $weeks = [];

    public string $label = '';

    public ?int $editId = null;

    public string $editLabel = '';

    public ?int $deleteTarget = null;

    public function mount(Year $year): void
    {
        $this->year = $year;
        $this->refresh();
    }

    public function createWeek(): void
    {
        $this->validate([
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique('weeks', 'label')->where(fn ($query) => $query->where('year_id', $this->year->id)),
            ],
        ]);

        app(WeekService::class)->create($this->year, [
            'label' => trim($this->label),
        ]);

        $this->reset('label');
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Semaine créée.'));
    }

    public function startEdit(int $id): void
    {
        $week = Week::findOrFail($id);

        $this->editId = $week->id;
        $this->editLabel = $week->label;

        $this->modal('edit-week')->show();
    }

    public function updateWeek(): void
    {
        $week = Week::findOrFail($this->editId);

        $this->validate([
            'editLabel' => ['required', 'string', 'max:255', Rule::unique('weeks', 'label')->where(fn ($query) => $query->where('year_id', $this->year->id))->ignore($week->id)],
        ]);

        app(WeekService::class)->update($week, [
            'label' => trim($this->editLabel),
        ]);

        $this->editId = null;
        $this->modal('edit-week')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Semaine mise à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-week')->show();
    }

    public function deleteWeek(): void
    {
        $week = Week::findOrFail($this->deleteTarget);

        if (! app(WeekService::class)->delete($week)) {
            Flux::toast(variant: 'danger', text: __('Cette semaine est encore utilisée.'));

            return;
        }

        $this->deleteTarget = null;
        $this->refresh();

        $this->modal('delete-week')->close();

        Flux::toast(variant: 'success', text: __('Semaine supprimée.'));
    }

    private function refresh(): void
    {
        $this->weeks = $this->year->weeks()
            ->orderBy('label')
            ->get()
            ->map(fn (Week $week): array => [
                'id' => $week->id,
                'label' => $week->label,
            ])
            ->all();
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Semaines') }}</flux:heading>
            <flux:text class="mt-1">{{ $this->year->label }}</flux:text>
        </div>

        <flux:button variant="ghost" :href="route('admin.years.index')" wire:navigate>
            {{ __('Retour aux années') }}
        </flux:button>
    </div>

    <form wire:submit="createWeek" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouvelle semaine') }}</flux:heading>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Étiquette / numéro') }}</flux:label>
                    <flux:input wire:model="label" placeholder="Semaine 1" />
                    <flux:error name="label" />
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
                <flux:table.column>{{ __('Semaine') }}</flux:table.column>
                <flux:table.column class="w-56" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($weeks as $week)
                    <flux:table.row>
                        <flux:table.cell>{{ $week['label'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" :href="route('admin.years.weeks.programs.index', [$this->year->id, $week['id']])" wire:navigate>
                                {{ __('Programmes') }}
                            </flux:button>

                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $week['id'] }})">
                                {{ __('Modifier') }}
                            </flux:button>

                            <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $week['id'] }})">
                                {{ __('Supprimer') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2" class="text-center text-zinc-400">
                            {{ __('Aucune semaine créée pour cette année.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-week" :dismissible="false">
        <form wire:submit="updateWeek">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier la semaine') }}</flux:heading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Étiquette / numéro') }}</flux:label>
                    <flux:input wire:model="editLabel" required />
                    <flux:error name="editLabel" />
                </flux:field>

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

    <flux:modal name="delete-week" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer cette semaine ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteWeek">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
