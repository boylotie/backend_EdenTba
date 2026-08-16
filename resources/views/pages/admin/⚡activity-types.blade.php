<?php

use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Services\ActivityTypeService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Types d\'activités')] class extends Component {
    /**
     * @var list<array{id: int, code: string, label: string, is_active: bool}>
     */
    public array $types = [];

    public string $code = '';

    public string $label = '';

    public ?int $editId = null;

    public string $editCode = '';

    public string $editLabel = '';

    public ?int $deleteTarget = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function createType(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/', Rule::unique('activity_types', 'code')],
            'label' => ['required', 'string', 'max:255'],
        ]);

        app(ActivityTypeService::class)->create([
            'code' => trim($this->code),
            'label' => trim($this->label),
            'is_active' => true,
        ]);

        $this->reset('code', 'label');
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Type créé.'));
    }

    public function toggleActive(int $id): void
    {
        $type = ActivityType::findOrFail($id);

        app(ActivityTypeService::class)->update($type, [
            'code' => $type->code,
            'label' => $type->label,
            'is_active' => ! $type->is_active,
        ]);

        $this->refresh();

        Flux::toast(variant: 'success', text: __('Type mis à jour.'));
    }

    public function startEdit(int $id): void
    {
        $type = ActivityType::findOrFail($id);

        $this->editId = $type->id;
        $this->editCode = $type->code;
        $this->editLabel = $type->label;

        $this->modal('edit-type')->show();
    }

    public function updateType(): void
    {
        $type = ActivityType::findOrFail($this->editId);

        $this->validate([
            'editCode' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/', Rule::unique('activity_types', 'code')->ignore($type->id)],
            'editLabel' => ['required', 'string', 'max:255'],
        ]);

        app(ActivityTypeService::class)->update($type, [
            'code' => trim($this->editCode),
            'label' => trim($this->editLabel),
            'is_active' => $type->is_active,
        ]);

        $this->editId = null;
        $this->modal('edit-type')->close();
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Type mis à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-type')->show();
    }

    public function deleteType(): void
    {
        $type = ActivityType::findOrFail($this->deleteTarget);

        if (! app(ActivityTypeService::class)->delete($type)) {
            Flux::toast(variant: 'danger', text: __('Ce type est encore utilisé.'));

            return;
        }

        $this->deleteTarget = null;
        $this->refresh();

        $this->modal('delete-type')->close();

        Flux::toast(variant: 'success', text: __('Type supprimé.'));
    }

    private function refresh(): void
    {
        $this->types = ActivityType::query()
            ->orderBy('label')
            ->get()
            ->map(fn (ActivityType $type): array => [
                'id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'is_active' => $type->is_active,
            ])
            ->all();
    }
}; ?>

<section class="w-full">
    <div>
        <flux:heading size="xl" level="1">{{ __('Types d\'activités') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Types configurables utilisés par les activités spéciales.') }}</flux:text>
    </div>

    <form wire:submit="createType" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouveau type') }}</flux:heading>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="code" placeholder="prayer" />
                    <flux:error name="code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Libellé') }}</flux:label>
                    <flux:input wire:model="label" placeholder="{{ __('Prière') }}" />
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
                <flux:table.column>{{ __('Libellé') }}</flux:table.column>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column class="w-56" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($types as $type)
                    <flux:table.row>
                        <flux:table.cell>{{ $type['label'] }}</flux:table.cell>
                        <flux:table.cell><code>{{ $type['code'] }}</code></flux:table.cell>
                        <flux:table.cell>
                            @if ($type['is_active'])
                                <flux:badge color="green">{{ __('Actif') }}</flux:badge>
                            @else
                                <flux:badge color="red">{{ __('Inactif') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $type['id'] }})">
                                {{ __('Modifier') }}
                            </flux:button>

                            <flux:button size="xs" variant="subtle" wire:click="toggleActive({{ $type['id'] }})">
                                {{ $type['is_active'] ? __('Désactiver') : __('Activer') }}
                            </flux:button>

                            <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $type['id'] }})">
                                {{ __('Supprimer') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-400">
                            {{ __('Aucun type créé.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-type" :dismissible="false">
        <form wire:submit="updateType">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier le type') }}</flux:heading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Code') }}</flux:label>
                    <flux:input wire:model="editCode" placeholder="prayer" />
                    <flux:error name="editCode" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Libellé') }}</flux:label>
                    <flux:input wire:model="editLabel" />
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

    <flux:modal name="delete-type" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer ce type ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteType">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
