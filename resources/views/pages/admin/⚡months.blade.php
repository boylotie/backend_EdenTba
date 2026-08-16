<?php

use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\MonthService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mois & thèmes')] class extends Component {
    public Year $year;

    /**
     * @var list<array{id: int, month_number: int, name: string, theme: string|null}>
     */
    public array $months = [];

    public ?int $editId = null;

    public string $editMonthName = '';

    public string $editTheme = '';

    public function mount(Year $year): void
    {
        $this->year = $year;
        $this->refresh();
    }

    public function startEdit(int $id): void
    {
        $month = Month::findOrFail($id);

        $this->editId = $month->id;
        $this->editMonthName = Month::NAMES[$month->month_number] ?? (string) $month->month_number;
        $this->editTheme = $month->theme ?? '';

        $this->modal('edit-month')->show();
    }

    public function updateMonth(): void
    {
        $month = Month::findOrFail($this->editId);

        $this->validate([
            'editTheme' => ['nullable', 'string', 'max:255'],
        ]);

        app(MonthService::class)->update($month, [
            'month_number' => $month->month_number,
            'theme' => $this->editTheme !== '' ? trim($this->editTheme) : null,
        ]);

        $this->editId = null;
        $this->modal('edit-month')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Thème du mois mis à jour.'));
    }

    private function refresh(): void
    {
        $this->months = $this->year->months()
            ->orderBy('month_number')
            ->get()
            ->map(fn (Month $month): array => [
                'id' => $month->id,
                'month_number' => $month->month_number,
                'name' => Month::NAMES[$month->month_number] ?? (string) $month->month_number,
                'theme' => $month->theme,
            ])
            ->all();
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Mois & thèmes') }}</flux:heading>
            <flux:text class="mt-1">{{ $this->year->label }}</flux:text>
        </div>

        <flux:button variant="ghost" :href="route('admin.years.index')" wire:navigate>
            {{ __('Retour aux années') }}
        </flux:button>
    </div>

    <flux:card class="my-6">
        <flux:text>
            {{ __('Les 12 mois de l\'année (janvier à décembre) sont créés automatiquement. Choisissez les mois et attribuez-leur un thème.') }}
        </flux:text>
    </flux:card>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Mois') }}</flux:table.column>
                <flux:table.column>{{ __('Thème') }}</flux:table.column>
                <flux:table.column class="w-44" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($months as $month)
                    <flux:table.row>
                        <flux:table.cell>{{ $month['name'] }}</flux:table.cell>
                        <flux:table.cell>{{ $month['theme'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $month['id'] }})">
                                {{ __('Attribuer un thème') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center text-zinc-400">
                            {{ __('Les 12 mois de cette année n\'ont pas encore été générés.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-month" :dismissible="false">
        <form wire:submit="updateMonth">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Attribuer un thème') }}</flux:heading>
                    <flux:text class="mt-2">{{ $this->editMonthName }}</flux:text>
                </div>

                <flux:field>
                    <flux:label>{{ __('Thème mensuel') }}</flux:label>
                    <flux:input wire:model="editTheme" placeholder="Espérance & renouveau" />
                    <flux:error name="editTheme" />
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
</section>
