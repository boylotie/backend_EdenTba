<?php

use App\Settings\SettingsDefinition;
use App\Settings\SettingsService;
use App\Shared\Audit\AuditLogger;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Paramètres système')] class extends Component {
    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    public function mount(): void
    {
        $this->values = app(SettingsService::class)->all();
    }

    public function save(): void
    {
        $rules = [];

        foreach (SettingsDefinition::validationRules() as $key => $keyRules) {
            $rules['values.'.$key] = $keyRules;
        }

        $this->validate($rules);

        app(SettingsService::class)->replace($this->values);

        AuditLogger::log('settings.update', ['keys' => array_keys($this->values)]);

        $this->values = app(SettingsService::class)->all();

        Flux::toast(variant: 'success', text: __('Paramètres enregistrés.'));
    }

    /**
     * @return array<string, array<string, array{type: string, default: mixed, label: string, group: string, public?: bool, min?: int, max?: int}>>
     */
    public function groups(): array
    {
        return SettingsDefinition::groups();
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Paramètres système') }}</flux:heading>

    <form wire:submit="save" class="my-6 w-full space-y-6">
        @foreach ($this->groups() as $group => $fields)
            <flux:card>
                <div class="mb-4">
                    <flux:heading>{{ $group }}</flux:heading>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    @foreach ($fields as $key => $definition)
                        <flux:field>
                            <flux:label>{{ $definition['label'] }}</flux:label>
                            @if ($definition['type'] === \App\Settings\SettingsDefinition::TYPE_BOOLEAN)
                                <flux:switch wire:model="values.{{ $key }}" />
                            @elseif ($definition['type'] === \App\Settings\SettingsDefinition::TYPE_INTEGER)
                                <flux:input type="number" wire:model="values.{{ $key }}" />
                            @else
                                <flux:input :type="($definition['secret'] ?? false) ? 'password' : 'text'" wire:model="values.{{ $key }}" />
                            @endif

                            <flux:error name="values.{{ $key }}" />
                        </flux:field>
                    @endforeach
                </div>
            </flux:card>
        @endforeach

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit">
                {{ __('Enregistrer') }}
            </flux:button>
        </div>
    </form>
</section>
