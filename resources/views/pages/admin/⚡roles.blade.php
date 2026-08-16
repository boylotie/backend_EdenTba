<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Shared\Audit\AuditLogger;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rôles & permissions')] class extends Component {
    public string $name = '';

    public string $label = '';

    /**
     * @var list<int>
     */
    public array $permissionIds = [];

    public ?int $editId = null;

    public string $editName = '';

    public string $editLabel = '';

    /**
     * @var list<int>
     */
    public array $editPermissionIds = [];

    public ?int $deleteTarget = null;

    /**
     * @var array<int, list<int>>
     */
    public array $assignments = [];

    public function getRolesProperty(): array
    {
        return Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label ?? $role->name,
                'users_count' => $role->users_count,
                'permission_ids' => $role->permissions()->pluck('permission_id')->all(),
            ])
            ->all();
    }

    public function getUsersProperty(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);
    }

    public function permissionGroups(): array
    {
        $groups = [];

        foreach (Permission::query()->orderBy('name')->get() as $permission) {
            $prefix = strstr((string) $permission->name, '.', true) ?: $permission->name;
            $groups[$prefix][] = ['id' => $permission->id, 'name' => $permission->name];
        }

        return $groups;
    }

    public function mount(): void
    {
        $this->refreshAssignments();
    }

    public function createRole(): void
    {
        abort_unless(auth()->user()->can('create', Role::class), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'label' => ['nullable', 'string', 'max:255'],
            'permissionIds' => ['array'],
            'permissionIds.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => trim($this->name),
            'label' => $this->label !== '' ? trim($this->label) : null,
        ]);

        $role->permissions()->sync($this->permissionIds);

        AuditLogger::log('roles.create', ['role_name' => $role->name, 'permissions' => $this->permissionIds], entityType: 'role', entityId: $role->id);

        $this->reset('name', 'label', 'permissionIds');

        Flux::toast(variant: 'success', text: __('Rôle créé.'));
    }

    public function startEdit(int $id): void
    {
        $role = Role::findOrFail($id);

        abort_unless(auth()->user()->can('update', $role), 403);

        $this->editId = $role->id;
        $this->editName = $role->name;
        $this->editLabel = $role->label ?? '';
        $this->editPermissionIds = $role->permissions()->pluck('permission_id')->all();

        $this->modal('edit-role')->show();
    }

    public function updateRole(): void
    {
        $role = Role::findOrFail($this->editId);

        abort_unless(auth()->user()->can('update', $role), 403);

        $this->validate([
            'editName' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'editLabel' => ['nullable', 'string', 'max:255'],
            'editPermissionIds' => ['array'],
            'editPermissionIds.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update([
            'name' => trim($this->editName),
            'label' => $this->editLabel !== '' ? trim($this->editLabel) : null,
        ]);

        $role->permissions()->sync($this->editPermissionIds);

        AuditLogger::log('roles.update', ['role_name' => $role->name, 'permissions' => $this->editPermissionIds], entityType: 'role', entityId: $role->id);

        $this->editId = null;
        $this->modal('edit-role')->close();

        Flux::toast(variant: 'success', text: __('Rôle mis à jour.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-role')->show();
    }

    public function deleteRole(): void
    {
        $role = Role::findOrFail($this->deleteTarget);

        abort_unless(auth()->user()->can('delete', $role), 403);

        if ($role->name === Role::SUPER_ADMIN) {
            Flux::toast(variant: 'danger', text: __('Le rôle super administrateur ne peut pas être supprimé.'));

            return;
        }

        if ($role->users()->exists()) {
            Flux::toast(variant: 'danger', text: __('Ce rôle est encore attribué à des utilisateurs.'));

            return;
        }

        $role->delete();

        AuditLogger::log('roles.delete', ['role_name' => $role->name], entityType: 'role', entityId: $role->id);

        $this->deleteTarget = null;
        $this->modal('delete-role')->close();

        Flux::toast(variant: 'success', text: __('Rôle supprimé.'));
    }

    public function saveRoles(int $userId): void
    {
        $user = User::findOrFail($userId);

        abort_unless(auth()->user()->can('manageRoles', $user), 403);

        $names = Role::whereIn('id', $this->assignments[$userId] ?? [])->pluck('name')->all();

        $current = $user->roles->pluck('name')->all();

        if (in_array(Role::SUPER_ADMIN, $current, true) && ! in_array(Role::SUPER_ADMIN, $names, true)) {
            $count = Role::where('name', Role::SUPER_ADMIN)->firstOrFail()->users()->count();

            if ($count <= 1) {
                Flux::toast(variant: 'danger', text: __('Impossible de retirer le dernier super administrateur.'));

                return;
            }
        }

        $user->syncRoles($names);

        AuditLogger::log('users.roles.update', ['roles' => $names], entityType: 'user', entityId: $user->id);

        Flux::toast(variant: 'success', text: __('Rôles mis à jour.'));
    }

    public function refreshAssignments(): void
    {
        $this->assignments = [];

        foreach ($this->users as $user) {
            $this->assignments[$user->id] = $user->roles->pluck('id')->all();
        }
    }

    public function updatedPage(): void
    {
        $this->refreshAssignments();
    }

    public function can(string $ability): bool
    {
        return match ($ability) {
            'createRole' => auth()->user()->can('create', Role::class),
            'manageRoles' => auth()->user()->can('manageRoles', new User),
            default => false,
        };
    }
}; ?>

<section class="w-full">
    <div>
        <flux:heading size="xl" level="1">{{ __('Rôles & permissions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Définissez les rôles, leurs permissions et les attributions aux utilisateurs.') }}</flux:text>
    </div>

    @if ($this->can('createRole'))
        <form wire:submit="createRole" class="my-6">
            <flux:card>
                <flux:heading>{{ __('Nouveau rôle') }}</flux:heading>

                <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Nom technique') }}</flux:label>
                        <flux:input wire:model="name" placeholder="moderator" required />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Libellé') }}</flux:label>
                        <flux:input wire:model="label" placeholder="{{ __('Modérateur') }}" />
                        <flux:error name="label" />
                    </flux:field>
                </div>

                <div class="mt-6">
                    <flux:heading size="sm">{{ __('Permissions') }}</flux:heading>

                    <div class="mt-3 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->permissionGroups() as $prefix => $permissions)
                            <div>
                                <div class="mb-1 text-sm font-medium text-zinc-500">{{ $prefix }}.*</div>
                                <div class="space-y-1">
                                    @foreach ($permissions as $permission)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" value="{{ $permission['id'] }}" wire:model="permissionIds">
                                            {{ $permission['name'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <flux:error name="permissionIds" />
                </div>

                <div class="mt-4 flex items-center justify-end">
                    <flux:button variant="primary" type="submit">{{ __('Créer le rôle') }}</flux:button>
                </div>
            </flux:card>
        </form>
    @endif

    <flux:card>
        <flux:heading>{{ __('Rôles existants') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Rôle') }}</flux:table.column>
                <flux:table.column>{{ __('Nom technique') }}</flux:table.column>
                <flux:table.column>{{ __('Permissions') }}</flux:table.column>
                <flux:table.column>{{ __('Utilisateurs') }}</flux:table.column>
                <flux:table.column class="w-56" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->roles as $role)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $role['label'] }}</flux:table.cell>
                        <flux:table.cell><code>{{ $role['name'] }}</code></flux:table.cell>
                        <flux:table.cell>{{ count($role['permission_ids']) }}</flux:table.cell>
                        <flux:table.cell>{{ $role['users_count'] }}</flux:table.cell>
                        <flux:table.cell>
                            @if (auth()->user()->can('update', \App\Models\Role::find($role['id'])))
                                <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $role['id'] }})">
                                    {{ __('Modifier') }}
                                </flux:button>
                            @endif

                            @if (auth()->user()->can('delete', \App\Models\Role::find($role['id'])))
                                <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $role['id'] }})">
                                    {{ __('Supprimer') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('Aucun rôle.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($this->can('manageRoles'))
        <flux:card class="mt-6">
            <flux:heading>{{ __('Utilisateurs & rôles') }}</flux:heading>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>{{ __('Utilisateur') }}</flux:table.column>
                    <flux:table.column>{{ __('Rôles') }}</flux:table.column>
                    <flux:table.column class="w-32" />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-sm text-zinc-400">{{ $user->email }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->roles as $role)
                                        <label class="flex items-center gap-1 text-sm">
                                            <input type="checkbox" value="{{ $role['id'] }}" wire:model="assignments.{{ $user->id }}">
                                            {{ $role['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" variant="subtle" wire:click="saveRoles({{ $user->id }})">
                                    {{ __('Enregistrer') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="p-4">
                {{ $this->users->links() }}
            </div>
        </flux:card>
    @endif

    <flux:modal name="edit-role" :dismissible="false" class="w-full max-w-3xl">
        <form wire:submit="updateRole">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier le rôle') }}</flux:heading>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Nom technique') }}</flux:label>
                        <flux:input wire:model="editName" required />
                        <flux:error name="editName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Libellé') }}</flux:label>
                        <flux:input wire:model="editLabel" />
                        <flux:error name="editLabel" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->permissionGroups() as $prefix => $permissions)
                        <div>
                            <div class="mb-1 text-sm font-medium text-zinc-500">{{ $prefix }}.*</div>
                            <div class="space-y-1">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" value="{{ $permission['id'] }}" wire:model="editPermissionIds">
                                        {{ $permission['name'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
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

    <flux:modal name="delete-role" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer ce rôle ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Cette action est définitive et irréversible.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteRole">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
