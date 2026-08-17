<?php

use App\Modules\Speakers\Models\Speaker;
use App\Modules\Speakers\Services\SpeakerService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Conférenciers')] class extends Component
{
    /**
     * @var list<array{id: int, name: string, title: string, bio: string|null, is_active: bool}>
     */
    public array $rows = [];

    public string $name = '';

    public string $title = 'autre';

    public ?int $editId = null;

    public string $editName = '';

    public string $editTitle = 'autre';

    public ?int $deleteTarget = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function createSpeaker(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'in:'.implode(',', Speaker::titleKeys())],
        ]);

        app(SpeakerService::class)->create([
            'name' => trim($this->name),
            'title' => $this->title,
            'is_active' => true,
        ]);

        $this->reset('name', 'title');
        $this->title = 'autre';
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Conférencier créé.'));
    }

    public function toggleActive(int $id): void
    {
        $speaker = Speaker::findOrFail($id);

        app(SpeakerService::class)->update($speaker, [
            'name' => $speaker->name,
            'title' => $speaker->title,
            'is_active' => ! $speaker->is_active,
        ]);

        $this->refresh();

        Flux::toast(variant: 'success', text: __('Conférencier mis à jour.'));
    }

    public function startEdit(int $id): void
    {
        $speaker = Speaker::findOrFail($id);

        $this->editId = $speaker->id;
        $this->editName = $speaker->name;
        $this->editTitle = $speaker->title;

        $this->modal('edit-speaker')->show();
    }

    public function updateSpeaker(): void
    {
        $speaker = Speaker::findOrFail($this->editId);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editTitle' => ['required', 'string', 'in:'.implode(',', Speaker::titleKeys())],
        ]);

        app(SpeakerService::class)->update($speaker, [
            'name' => trim($this->editName),
            'title' => $this->editTitle,
            'is_active' => $speaker->is_active,
        ]);

        $this->editId = null;
        $this->modal('edit-speaker')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Conférencier mis à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-speaker')->show();
    }

    public function deleteSpeaker(): void
    {
        $speaker = Speaker::findOrFail($this->deleteTarget);

        if (! app(SpeakerService::class)->delete($speaker)) {
            Flux::toast(variant: 'danger', text: __('Ce conférencier est encore utilisé.'));

            return;
        }

        $this->deleteTarget = null;
        $this->refresh();

        $this->modal('delete-speaker')->close();

        Flux::toast(variant: 'success', text: __('Conférencier supprimé.'));
    }

    private function refresh(): void
    {
        $this->rows = Speaker::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Speaker $speaker): array => [
                'id' => $speaker->id,
                'name' => $speaker->name,
                'title' => $speaker->title,
                'bio' => $speaker->bio,
                'is_active' => $speaker->is_active,
            ])
            ->all();
    }
}; ?>

<section class="w-full" wire:poll.10s="refresh">
    <div>
        <flux:heading size="xl" level="1">{{ __('Conférenciers') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Gestion des pasteurs et conférenciers.') }}</flux:text>
    </div>

    <form wire:submit="createSpeaker" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouveau conférencier') }}</flux:heading>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model="name" placeholder="{{ __('Jean Dupont') }}" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Titre') }}</flux:label>
                    <flux:select wire:model="title">
                        @foreach (\App\Modules\Speakers\Models\Speaker::TITLES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="title" />
                </flux:field>

                <flux:field class="flex items-end">
                    <flux:button variant="primary" type="submit">{{ __('Créer') }}</flux:button>
                </flux:field>
            </div>
        </flux:card>
    </form>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Titre') }}</flux:table.column>
                <flux:table.column>{{ __('Bio') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column class="w-56" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row>
                        <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                        <flux:table.cell>
                            {{ \App\Modules\Speakers\Models\Speaker::TITLES[$row['title']] ?? $row['title'] }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($row['bio'])
                                <span class="text-zinc-400">{{ Str::limit($row['bio'], 60) }}</span>
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($row['is_active'])
                                <flux:badge color="green">{{ __('Actif') }}</flux:badge>
                            @else
                                <flux:badge color="red">{{ __('Inactif') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $row['id'] }})">
                                {{ __('Modifier') }}
                            </flux:button>

                            <flux:button size="xs" variant="subtle" wire:click="toggleActive({{ $row['id'] }})">
                                {{ $row['is_active'] ? __('Désactiver') : __('Activer') }}
                            </flux:button>

                            <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $row['id'] }})">
                                {{ __('Supprimer') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('Aucun conférencier créé.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-speaker" :dismissible="false">
        <form wire:submit="updateSpeaker">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier le conférencier') }}</flux:heading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Titre') }}</flux:label>
                    <flux:select wire:model="editTitle">
                        @foreach (\App\Modules\Speakers\Models\Speaker::TITLES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editTitle" />
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

    <flux:modal name="delete-speaker" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer ce conférencier ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteSpeaker">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
