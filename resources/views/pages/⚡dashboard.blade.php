<?php

use App\Modules\Analytics\Services\StatisticsService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Tableau de bord')] class extends Component {
    #[Url(except: '30d')]
    public string $period = '30d';

    public function report(): array
    {
        return app(StatisticsService::class)->report($this->period, 10);
    }
}; ?>

<section class="w-full">
    @php($report = $this->report())

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Tableau de bord') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Période du') }} {{ $report['period']['start_date'] }}
                {{ __('au') }} {{ $report['period']['end_date'] }}
            </flux:text>
        </div>

        <flux:select wire:model.live="period" class="w-40">
            <option value="7d">{{ __('7 jours') }}</option>
            <option value="30d">{{ __('30 jours') }}</option>
            <option value="90d">{{ __('90 jours') }}</option>
        </flux:select>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:card>
            <flux:heading size="lg">{{ $report['totals']['plays'] }}</flux:heading>
            <flux:text class="mt-1">{{ __('Lectures') }}</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">{{ $report['totals']['completions'] }}</flux:heading>
            <flux:text class="mt-1">{{ __('Écoutes terminées') }}</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">{{ $report['totals']['contents'] }}</flux:heading>
            <flux:text class="mt-1">{{ __('Contenus écoutés') }}</flux:text>
        </flux:card>
    </div>

    @if ($report['empty'])
        <flux:card class="mt-6">
            <div class="py-8 text-center">
                <flux:heading>{{ __('Aucune donnée pour cette période.') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Les statistiques apparaîtront dès les premières écoutes.') }}</flux:text>
            </div>
        </flux:card>
    @else
        <flux:card class="mt-6">
            <flux:heading>{{ __('Contenus les plus écoutés') }}</flux:heading>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>{{ __('Contenu') }}</flux:table.column>
                    <flux:table.column>{{ __('Lectures') }}</flux:table.column>
                    <flux:table.column>{{ __('Écoutes terminées') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($report['by_content'] as $row)
                        <flux:table.row>
                            <flux:table.cell>{{ $row['title'] ?? __('Contenu supprimé') }}</flux:table.cell>
                            <flux:table.cell>{{ $row['plays'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['completions'] }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-400">
                                {{ __('Aucun contenu écouté.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</section>
