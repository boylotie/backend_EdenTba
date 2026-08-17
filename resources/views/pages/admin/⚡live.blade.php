<?php

use App\Modules\Streaming\Models\LiveSession;
use App\Modules\Streaming\Services\LiveService;
use App\Settings\SettingsService;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Direct (live)')] class extends Component {
    use WithFileUploads;

    public $imageFile;

    public string $title = '';

    public string $description = '';

    public function startLive(): void
    {
        $this->authorize('start', LiveSession::class);

        $this->validate([
            'imageFile' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            app(LiveService::class)->start([
                'title' => $this->title !== '' ? trim($this->title) : null,
                'description' => $this->description !== '' ? trim($this->description) : null,
            ], $this->imageFile);
        } catch (DomainException) {
            Flux::toast(variant: 'danger', text: __('Un direct est déjà en cours.'));

            return;
        }

        $this->reset('imageFile', 'title', 'description');

        Flux::toast(variant: 'success', text: __('Direct lancé.'));
    }

    public function stopLive(): void
    {
        $this->authorize('stop', LiveSession::class);

        try {
            app(LiveService::class)->stop();
        } catch (DomainException) {
            Flux::toast(variant: 'danger', text: __('Aucun direct en cours.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Direct arrêté.'));
    }

    public function canStart(): bool
    {
        return auth()->user()->hasPermission('streaming.start');
    }

    public function canStop(): bool
    {
        return auth()->user()->hasPermission('streaming.stop');
    }

    public function getCurrentProperty(): ?LiveSession
    {
        return app(LiveService::class)->current();
    }

    public function getHistoryProperty(): Collection
    {
        return LiveSession::query()
            ->with('creator:id,name')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function dateLabel(?CarbonInterface $value): string
    {
        return $value?->format('d/m/Y H:i') ?? '—';
    }

    public function durationLabel(?LiveSession $session): string
    {
        if ($session === null || $session->started_at === null) {
            return '—';
        }

        $seconds = $session->started_at->diffInSeconds($session->stopped_at ?? now());

        if ($seconds < 60) {
            return "{$seconds} s";
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $rest > 0 ? "{$minutes} min {$rest} s" : "{$minutes} min";
    }

    public function stateLabel(string $state): string
    {
        return $state === LiveSession::STATE_LIVE ? __('En direct') : __('Arrêté');
    }

    public function stateColor(string $state): string
    {
        return $state === LiveSession::STATE_LIVE ? 'red' : 'gray';
    }

    public bool $showPassword = false;

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function sourceUrl(): string
    {
        return (string) app(SettingsService::class)->get('stream_source_url');
    }

    public function sourcePassword(): string
    {
        return (string) app(SettingsService::class)->get('stream_source_password');
    }

    public function listenUrl(): string
    {
        return (string) app(SettingsService::class)->get('stream_url_base');
    }
}; ?>

<section class="w-full" wire:poll.30s>
    @php($current = $this->current)
    @php($isLive = $current !== null && $current->isLive())

    <div>
        <flux:heading size="xl" level="1">{{ __('Direct (live)') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Lancer et arrêter la diffusion en direct, avec un titre et un visuel.') }}</flux:text>
    </div>

    <flux:card class="my-6">
        <flux:heading>{{ __('Serveur de diffusion (encodeur)') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('Envoyez le flux audio vers le serveur de diffusion pour que les auditeurs puissent écouter : configurez votre encodeur (OBS, BUTT, Mixxx…) avec les accès ci-dessous.') }}
        </flux:text>

        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('URL de la source (diffusion)') }}</flux:label>
                <flux:input readonly :value="$this->sourceUrl()" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Mot de passe de la source') }}</flux:label>
                <div class="flex items-center gap-2">
                    <flux:input readonly :type="$this->showPassword ? 'text' : 'password'" :value="$this->sourcePassword()" class="flex-1" />
                    <flux:button variant="ghost" size="sm" wire:click="togglePassword">
                        {{ $this->showPassword ? __('Masquer') : __('Afficher') }}
                    </flux:button>
                </div>
            </flux:field>

            <flux:field class="lg:col-span-2">
                <flux:label>{{ __('URL d\'écoute (auditeurs)') }}</flux:label>
                <flux:input readonly :value="$this->listenUrl()" />
            </flux:field>
        </div>

        <flux:text class="mt-4">
            {{ __('Lancez le direct puis démarrez l\'encodeur : les auditeurs entendront le flux à l\'URL d\'écoute ci-dessus.') }}
        </flux:text>
    </flux:card>

    @if ($isLive)
        <flux:card class="my-6">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <flux:badge color="red" size="sm">{{ __('EN DIRECT') }}</flux:badge>
                    <flux:text>{{ __('Début') }} : {{ $this->dateLabel($current->started_at) }}</flux:text>
                </div>

                @if ($current->image_path !== null)
                    <img src="{{ url('/api/v1/live/image') }}" alt="{{ $current->title ?? __('Direct') }}" class="w-full max-w-md rounded-lg">
                @endif

                @if ($current->title !== null)
                    <flux:heading size="lg">{{ $current->title }}</flux:heading>
                @endif

                @if ($current->description !== null)
                    <flux:text>{{ $current->description }}</flux:text>
                @endif

                <flux:text>
                    {{ __('N\'oubliez pas d\'envoyer le flux audio vers le serveur de diffusion pour que les auditeurs puissent écouter.') }}
                </flux:text>

                <flux:card class="mt-2" wire:ignore>
                    <flux:heading>{{ __('Capturer le micro (diffusion navigateur)') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Aucun encodeur externe requis : l\'audio du micro est envoyé au serveur de diffusion et les auditeurs l\'entendent en direct.') }}
                    </flux:text>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <flux:button id="mic-start" variant="primary">{{ __('Démarrer le micro') }}</flux:button>
                        <flux:button id="mic-stop" variant="danger" disabled>{{ __('Arrêter le micro') }}</flux:button>
                        <span id="mic-status" class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Micro arrêté') }}</span>
                    </div>

                    <p id="mic-error" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>

                    <flux:text class="mt-2">
                        {{ __('Démarrez le relais sur le serveur pendant le direct : php artisan live:relay. Il pousse l\'audio vers le serveur de diffusion tant que le micro est actif.') }}
                    </flux:text>
                </flux:card>

                <div class="flex items-center gap-3">
                    <flux:spacer />
                    @if ($this->canStop())
                        <flux:button variant="danger" wire:click="stopLive">
                            {{ __('Arrêter le direct') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </flux:card>
    @else
        <form wire:submit="startLive" class="my-6">
            <flux:card>
                <flux:heading>{{ __('Lancer un direct') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Aucun direct en cours. Remplissez le titre puis lancez la diffusion ; le direct apparaît aussitôt dans l\'application.') }}
                </flux:text>

                <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Titre du direct') }}</flux:label>
                        <flux:input wire:model="title" placeholder="Culte du dimanche" />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Visuel de couverture') }}</flux:label>
                        <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                        <flux:error name="imageFile" />
                    </flux:field>

                    <flux:field class="lg:col-span-2">
                        <flux:label>{{ __('Description') }}</flux:label>
                        <flux:textarea wire:model="description" rows="3" />
                        <flux:error name="description" />
                    </flux:field>
                </div>

                <div class="mt-4 flex items-center justify-end">
                    @if ($this->canStart())
                        <flux:button variant="primary" type="submit">{{ __('Lancer le direct') }}</flux:button>
                    @endif
                </div>
            </flux:card>
        </form>
    @endif

    <flux:card>
        <flux:heading size="lg">{{ __('Historique des directs') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Titre') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column>{{ __('Début') }}</flux:table.column>
                <flux:table.column>{{ __('Fin') }}</flux:table.column>
                <flux:table.column>{{ __('Durée') }}</flux:table.column>
                <flux:table.column>{{ __('Lancé par') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->history as $session)
                    <flux:table.row>
                        <flux:table.cell>{{ $session->title ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$this->stateColor($session->state)" size="sm">
                                {{ $this->stateLabel($session->state) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->dateLabel($session->started_at) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->dateLabel($session->stopped_at) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->durationLabel($session) }}</flux:table.cell>
                        <flux:table.cell>{{ $session->creator?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400">
                            {{ __('Aucun direct pour le moment.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</section>

@script
<script>
    (() => {
        const chunkUrl = @json(route('admin.live.stream.chunk'));
        const stopUrl = @json(route('admin.live.stream.stop'));

        let recorder = null;
        let stream = null;
        let queue = Promise.resolve();
        let stopping = false;

        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const byId = (id) => document.getElementById(id);

        const setStatus = (text) => {
            const el = byId('mic-status');
            if (el) el.textContent = text;
        };

        const setError = (text) => {
            const el = byId('mic-error');
            if (el) el.textContent = text ?? '';
        };

        const setRunning = (running) => {
            const start = byId('mic-start');
            const stop = byId('mic-stop');
            if (start) start.disabled = running;
            if (stop) stop.disabled = ! running;
        };

        const stopTracks = () => {
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                stream = null;
            }
        };

        const pickMimeType = () => {
            if (typeof MediaRecorder === 'undefined' || ! MediaRecorder.isTypeSupported) return 'audio/webm';

            return [
                'audio/webm;codecs=opus',
                'audio/ogg;codecs=opus',
                'audio/webm',
                'audio/mp4',
            ].find((candidate) => MediaRecorder.isTypeSupported(candidate)) ?? 'audio/webm';
        };

        const enqueueChunk = (blob) => {
            queue = queue.then(() => sendChunk(blob)).catch(() => {});
        };

        async function sendChunk(blob) {
            if (blob.size === 0) return;

            const response = await fetch(chunkUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Content-Type': blob.type || 'audio/webm',
                    'Accept': 'application/json',
                },
                body: blob,
            });

            if (response.status === 409) {
                setError('Direct terminé : l’envoi de la capture a été arrêté.');
                await stopCapture();
            } else if (response.status === 422) {
                let message = 'Chunk refusé par le serveur.';
                try {
                    const body = await response.json();
                    if (body && body.message) message = body.message;
                } catch (e) {}
                setError(message);
                await stopCapture();
            }
        }

        async function startCapture() {
            if (recorder || stopping) return;

            if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
                setError('Le micro exige une connexion sécurisée (HTTPS) ou localhost.');
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (error) {
                if (error && error.name === 'NotAllowedError') {
                    setError('Accès au micro refusé : autorisez le micro dans le navigateur (icône verrou) puis réessayez.');
                } else if (error && (error.name === 'NotFoundError' || error.name === 'OverconstrainedError')) {
                    setError('Aucun microphone détecté sur cet appareil.');
                } else if (error && (error.name === 'NotReadableError')) {
                    setError('Micro déjà utilisé par une autre application.');
                } else {
                    setError('Accès au micro impossible ('.String(error?.name ?? 'inconnu').').');
                }
                return;
            }

            let mimeType;
            try {
                mimeType = pickMimeType();
            } catch (error) {
                stopTracks();
                setError('Enregistrement audio non supporté par ce navigateur.');
                return;
            }

            const options = { audioBitsPerSecond: 128000 };
            if (mimeType) options.mimeType = mimeType;

            try {
                recorder = new MediaRecorder(stream, options);
            } catch (error) {
                stopTracks();
                setError('Enregistrement audio non supporté par ce navigateur.');
                return;
            }

            recorder.ondataavailable = (event) => enqueueChunk(event.data);
            recorder.onstop = stopTracks;
            recorder.start(1000);

            setRunning(true);
            setStatus('Micro actif — diffusion en cours');
            setError('');
        }

        async function stopCapture() {
            if (stopping) return;
            stopping = true;

            if (recorder && recorder.state !== 'inactive') {
                recorder.stop();
            }
            stopTracks();
            setRunning(false);
            setStatus('Micro arrêté');

            try {
                await fetch(stopUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                });
            } catch (error) {
                // Réseau indisponible : l'état est réconcilié au prochain poll.
            }

            if (typeof $wire !== 'undefined' && $wire.$refresh) {
                $wire.$refresh();
            }

            recorder = null;
            stopping = false;
        }

        document.addEventListener('click', (event) => {
            if (event.target.closest('#mic-start')) {
                event.preventDefault();
                startCapture();
            }

            if (event.target.closest('#mic-stop')) {
                event.preventDefault();
                stopCapture();
            }
        });

        setInterval(() => {
            if (! byId('mic-start') && (recorder || stopping)) {
                stopCapture();
            }
        }, 2000);

        window.addEventListener('beforeunload', () => {
            if (recorder && recorder.state !== 'inactive') {
                recorder.stop();
            }
            stopTracks();
        });
    })();
</script>
@endscript
