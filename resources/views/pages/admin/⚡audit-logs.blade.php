<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Journal d\'audit')] class extends Component {
    #[Url(except: '')]
    public string $actionFilter = '';

    #[Url(except: '')]
    public string $userFilter = '';

    #[Url(except: '')]
    public string $fromFilter = '';

    #[Url(except: '')]
    public string $toFilter = '';

    public function getRowsProperty(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('actor:id,name')
            ->when($this->actionFilter !== '', fn ($query) => $query->where('action', $this->actionFilter))
            ->when($this->userFilter !== '', fn ($query) => $query->where('actor_id', $this->userFilter))
            ->when($this->fromFilter !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->fromFilter))
            ->when($this->toFilter !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->toFilter))
            ->latest('id')
            ->paginate(15);
    }

    public function getActionOptionsProperty(): array
    {
        return AuditLog::query()->distinct()->orderBy('action')->pluck('action')->all();
    }

    public function getUserOptionsProperty(): array
    {
        return User::query()->orderBy('name')->get()->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all();
    }
}; ?>

<section class="w-full">
    @php($rows = $this->rows)

    <div>
        <flux:heading size="xl" level="1">{{ __('Journal d\'audit') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Actions sensibles enregistrées sur la plateforme.') }}</flux:text>
    </div>

    <flux:card class="my-6">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <flux:field>
                <flux:label>{{ __('Action') }}</flux:label>
                <flux:select wire:model.live="actionFilter">
                    <option value="">—</option>
                    @foreach ($this->actionOptions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Utilisateur') }}</flux:label>
                <flux:select wire:model.live="userFilter">
                    <option value="">—</option>
                    @foreach ($this->userOptions as $user)
                        <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Du') }}</flux:label>
                <flux:input type="date" wire:model.live="fromFilter" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Au') }}</flux:label>
                <flux:input type="date" wire:model.live="toFilter" />
            </flux:field>
        </div>
    </flux:card>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Acteur') }}</flux:table.column>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
                <flux:table.column>{{ __('Cible') }}</flux:table.column>
                <flux:table.column>{{ __('Adresse IP') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rows as $log)
                    <flux:table.row>
                        <flux:table.cell class="whitespace-nowrap text-sm">
                            {{ $log->created_at->format('d/m/Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $log->actor?->name ?? __('Système') }}</flux:table.cell>
                        <flux:table.cell><code class="text-sm">{{ $log->action }}</code></flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            @if ($log->entity_type)
                                {{ $log->entity_type }} #{{ $log->entity_id }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-400">{{ $log->ip_address ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('Aucune entrée d\'audit.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="p-4">
            {{ $rows->links() }}
        </div>
    </flux:card>
</section>
