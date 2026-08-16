<?php

use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\YearService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Années & thèmes')] class extends Component {
    /**
     * @var list<array{id: int, label: string, theme: string|null, is_current: bool}>
     */
    public array $years = [];

    public string $label = '';

    public string $theme = '';

    public bool $makeCurrent = false;

    public ?int $editId = null;

    public string $editLabel = '';

    public string $editTheme = '';

    public bool $editMakeCurrent = false;

    public ?int $deleteTarget = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function createYear(): void
    {
        $this->validate([
            'label' => ['required', 'string', 'max:255', Rule::unique('years', 'label')],
            'theme' => ['nullable', 'string', 'max:255'],
        ]);

        app(YearService::class)->create([
            'label' => trim($this->label),
            'theme' => $this->theme !== '' ? trim($this->theme) : null,
            'is_current' => $this->makeCurrent,
        ]);

        $this->reset(['label', 'theme', 'makeCurrent']);
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Année créée.'));
    }

    public function setCurrent(int $id): void
    {
        app(YearService::class)->markCurrent(Year::findOrFail($id));

        $this->refresh();

        Flux::toast(variant: 'success', text: __('Année courante mise à jour.'));
    }

    public function startEdit(int $id): void
    {
        $year = Year::findOrFail($id);

        $this->editId = $year->id;
        $this->editLabel = $year->label;
        $this->editTheme = $year->theme ?? '';
        $this->editMakeCurrent = $year->is_current;

        $this->modal('edit-year')->show();
    }

    public function updateYear(): void
    {
        $year = Year::findOrFail($this->editId);

        $this->validate([
            'editLabel' => ['required', 'string', 'max:255', Rule::unique('years', 'label')->ignore($year->id)],
            'editTheme' => ['nullable', 'string', 'max:255'],
        ]);

        app(YearService::class)->update($year, [
            'label' => trim($this->editLabel),
            'theme' => $this->editTheme !== '' ? trim($this->editTheme) : null,
            'is_current' => $this->editMakeCurrent,
        ]);

        $this->editId = null;
        $this->modal('edit-year')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Année mise à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-year')->show();
    }

    public function deleteYear(): void
    {
        $year = Year::findOrFail($this->deleteTarget);

        if (! app(YearService::class)->delete($year)) {
            Flux::toast(variant: 'danger', text: __('Cette année est encore utilisée.'));

            return;
        }

        $this->deleteTarget = null;
        $this->refresh();

        $this->modal('delete-year')->close();

        Flux::toast(variant: 'success', text: __('Année supprimée.'));
    }

    private function refresh(): void
    {
        $this->years = Year::query()
            ->orderBy('label')
            ->get()
            ->map(fn (Year $year): array => [
                'id' => $year->id,
                'label' => $year->label,
                'theme' => $year->theme,
                'is_current' => $year->is_current,
            ])
            ->all();
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Années & thèmes') }}</flux:heading>

    <form wire:submit="createYear" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouvelle année') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('La création génère automatiquement les 12 mois de l\'année (janvier à décembre).') }}
            </flux:text>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Libellé') }}</flux:label>
                    <flux:input wire:model="label" placeholder="2026-2027" required />
                    <flux:error name="label" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Thème annuel') }}</flux:label>
                    <flux:input wire:model="theme" placeholder="Foi & espérance" />
                    <flux:error name="theme" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Année courante') }}</flux:label>
                    <flux:switch wire:model="makeCurrent" />
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
                <flux:table.column>{{ __('Année') }}</flux:table.column>
                <flux:table.column>{{ __('Thème') }}</flux:table.column>
                <flux:table.column>{{ __('Courante') }}</flux:table.column>
                <flux:table.column class="w-72" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($years as $year)
                    <flux:table.row>
                        <flux:table.cell>{{ $year['label'] }}</flux:table.cell>
                        <flux:table.cell>{{ $year['theme'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($year['is_current'])
                                <flux:badge color="green" size="sm">{{ __('Courante') }}</flux:badge>
                            @else
                                <flux:button size="xs" wire:click="setCurrent({{ $year['id'] }})">
                                    {{ __('Désigner courante') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" :href="route('admin.years.months.index', $year['id'])" wire:navigate>
                                {{ __('Mois') }}
                            </flux:button>

                            <flux:button size="xs" variant="subtle" :href="route('admin.years.weeks.index', $year['id'])" wire:navigate>
                                {{ __('Semaines') }}
                            </flux:button>

                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $year['id'] }})">
                                {{ __('Modifier') }}
                            </flux:button>

                            <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $year['id'] }})">
                                {{ __('Supprimer') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-400">
                            {{ __('Aucune année créée.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-year" :dismissible="false">
        <form wire:submit="updateYear">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier l\'année') }}</flux:heading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Libellé') }}</flux:label>
                    <flux:input wire:model="editLabel" required />
                    <flux:error name="editLabel" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Thème annuel') }}</flux:label>
                    <flux:input wire:model="editTheme" />
                    <flux:error name="editTheme" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Année courante') }}</flux:label>
                    <flux:switch wire:model="editMakeCurrent" />
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

    <flux:modal name="delete-year" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer cette année ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteYear">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
