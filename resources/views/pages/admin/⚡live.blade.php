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

    public bool $showEncoder = false;

    public bool $showHistory = false;

    public function toggleEncoder(): void
    {
        $this->showEncoder = ! $this->showEncoder;
    }

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
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

    public function signedStreamUrl(): ?string
    {
        try {
            $session = app(LiveService::class)->current();

            if ($session === null || ! $session->isLive()) {
                return null;
            }

            $signed = app(LiveService::class)->signedStreamUrl($session);

            return $signed['url'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}; ?>

<section class="w-full" wire:poll.60s>
    @php($current = $this->current)
    @php($isLive = $current !== null && $current->isLive())

    <div>
        <flux:heading size="xl" level="1">{{ __('Direct (live)') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Lancer et arrêter la diffusion en direct, avec un titre et un visuel.') }}</flux:text>
    </div>

    <div class="my-6">
        <flux:button variant="ghost" size="sm" wire:click="toggleEncoder" class="mb-2">
            {{ $showEncoder ? __('▼ Masquer le serveur de diffusion') : __('▶ Serveur de diffusion (encodeur)') }}
        </flux:button>

        @if ($showEncoder)
            <flux:card>
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
        @endif
    </div>

    @if ($isLive)
        @php($startedAt = $current->started_at?->getTimestamp() ?? now()->getTimestamp())
        <flux:card class="my-6">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <flux:badge color="red" size="sm">{{ __('EN DIRECT') }}</flux:badge>
                    <flux:text>{{ __('Début') }} : {{ $this->dateLabel($current->started_at) }}</flux:text>
                    <span id="live-timer" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-sm font-mono font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"></span>
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

                <div wire:ignore class="mt-2 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold">{{ __('Signal & ondulation') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Visualisation en temps réel du flux audio en cours de diffusion.') }}
                    </p>

                    <div class="mt-4">
                        <div class="flex items-center gap-4 mb-3">
                            <span id="sig-status" class="text-sm text-zinc-500">{{ __('En attente du flux…') }}</span>
                            <div id="sig-level-wrap" class="hidden flex-1 max-w-xs h-3 bg-zinc-200 dark:bg-zinc-700 rounded overflow-hidden">
                                <div id="sig-level" class="h-full bg-green-500 transition-all duration-100" style="width:0%"></div>
                            </div>
                            <span id="sig-db" class="text-xs text-zinc-400 tabular-nums hidden"></span>
                        </div>
                        <canvas id="sig-waveform" class="w-full h-24 rounded bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700"></canvas>
                    </div>
                </div>

                <div x-data class="mt-2 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold">{{ __('Capturer le micro (diffusion navigateur)') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Aucun encodeur externe requis : l\'audio du micro est envoyé au serveur de diffusion et les auditeurs l\'entendent en direct.') }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button type="button" id="mic-start" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ __('Démarrer le micro') }}</button>
                        <button type="button" id="mic-stop" disabled class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">{{ __('Arrêter le micro') }}</button>
                        <span id="mic-status" class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Micro arrêté') }}</span>
                    </div>

                    <p id="mic-error" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>

                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Démarrez le relais sur le serveur pendant le direct : php artisan live:relay. Il pousse l\'audio vers le serveur de diffusion tant que le micro est actif.') }}
                    </p>
                </div>

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

    <div class="my-6">
        <flux:button variant="ghost" size="sm" wire:click="toggleHistory" class="mb-2">
            {{ $showHistory ? __('▼ Masquer l\'historique') : __('▶ Historique des directs') }}
        </flux:button>

        @if ($showHistory)
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
        @endif
    </div>

    @if ($isLive)
        @php($startedAt = $current->started_at?->getTimestamp() ?? now()->getTimestamp())
        @php($signedUrl = $this->signedStreamUrl())

        <script>
        (function () {
            var startTs = {{ $startedAt }};
            var el = document.getElementById('live-timer');
            if (!el) return;
            function tick() {
                var diff = Math.max(0, Math.floor(Date.now() / 1000) - startTs);
                var h = Math.floor(diff / 3600);
                var m = Math.floor((diff % 3600) / 60);
                var s = diff % 60;
                el.textContent = (h > 0 ? h + 'h ' : '') + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                requestAnimationFrame(tick);
            }
            tick();
        })();

        (function () {
            if (window.__sigMonitor) return;
            var streamUrl = @json($signedUrl);
            if (!streamUrl) return;

            var audioCtx = null;
            var analyser = null;
            var sourceNode = null;
            var audioEl = null;
            var rafId = null;
            var stopped = false;

            function byId(id) { return document.getElementById(id); }
            function setStatus(t) { var el = byId('sig-status'); if (el) el.textContent = t; }

            function initAudio() {
                if (audioCtx) return;
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioCtx.createAnalyser();
                analyser.fftSize = 2048;
                analyser.smoothingTimeConstant = 0.8;
                analyser.connect(audioCtx.destination);

                audioEl = new Audio();
                audioEl.crossOrigin = 'anonymous';
                audioEl.src = streamUrl;
                audioEl.preload = 'auto';
                audioEl.volume = 0;

                sourceNode = audioCtx.createMediaElementSource(audioEl);
                sourceNode.connect(analyser);
            }

            function drawWaveform() {
                if (stopped || !analyser) return;

                var canvas = byId('sig-waveform');
                if (!canvas) return;
                var ctx = canvas.getContext('2d');
                var W = canvas.width = canvas.offsetWidth * (window.devicePixelRatio || 1);
                var H = canvas.height = canvas.offsetHeight * (window.devicePixelRatio || 1);
                ctx.clearRect(0, 0, W, H);

                var bufLen = analyser.frequencyBinCount;
                var data = new Uint8Array(bufLen);
                analyser.getByteTimeDomainData(data);

                ctx.lineWidth = 2;
                ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--color-green-500') || '#22c55e';
                ctx.beginPath();

                var sliceW = W / bufLen;
                var x = 0;
                for (var i = 0; i < bufLen; i++) {
                    var v = data[i] / 128.0;
                    var y = (v * H) / 2;
                    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                    x += sliceW;
                }
                ctx.lineTo(W, H / 2);
                ctx.stroke();

                var sum = 0;
                for (var j = 0; j < bufLen; j++) {
                    var d = data[j] - 128;
                    sum += d * d;
                }
                var rms = Math.sqrt(sum / bufLen);
                var pct = Math.min(100, Math.round((rms / 64) * 100));
                var db = rms > 0 ? Math.round(20 * Math.log10(rms / 128)) : -60;

                var levelEl = byId('sig-level');
                var dbEl = byId('sig-db');
                var wrapEl = byId('sig-level-wrap');
                if (levelEl) levelEl.style.width = pct + '%';
                if (dbEl) { dbEl.textContent = db + ' dB'; dbEl.classList.remove('hidden'); }
                if (wrapEl) wrapEl.classList.remove('hidden');

                rafId = requestAnimationFrame(drawWaveform);
            }

            function start() {
                if (audioEl && !audioEl.paused) return;
                initAudio();
                stopped = false;
                audioEl.play().then(function () {
                    setStatus('Flux actif — visualisation en cours');
                    drawWaveform();
                }).catch(function (e) {
                    setStatus('Erreur lecture flux (' + (e.name || 'inconnu') + ')');
                });
            }

            window.__sigMonitor = { start: start };

            setTimeout(function () { start(); }, 600);
        })();

        (function () {
            if (window.__micCapture) return;

            var chunkUrl = @json(route('admin.live.stream.chunk'));
            var stopUrl  = @json(route('admin.live.stream.stop'));

            var recorder = null;
            var stream = null;
            var queue = Promise.resolve();
            var stopping = false;

            function csrf() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            }
            function byId(id) { return document.getElementById(id); }
            function setStatus(t) { var el = byId('mic-status'); if (el) el.textContent = t; }
            function setError(t) { var el = byId('mic-error'); if (el) el.textContent = t || ''; }
            function setRunning(r) {
                var s = byId('mic-start'), p = byId('mic-stop');
                if (s) s.disabled = r;
                if (p) p.disabled = !r;
            }
            function stopTracks() {
                if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            }
            function pickMimeType() {
                if (typeof MediaRecorder === 'undefined') return 'audio/webm';
                var types = ['audio/webm;codecs=opus','audio/ogg;codecs=opus','audio/webm','audio/mp4'];
                for (var i = 0; i < types.length; i++) { if (MediaRecorder.isTypeSupported(types[i])) return types[i]; }
                return 'audio/webm';
            }
            function enqueueChunk(blob) {
                if (stopping || !recorder) return;
                queue = queue.then(function () { return sendChunk(blob); }).catch(function () {});
            }
            function sendChunk(blob) {
                if (blob.size === 0) return Promise.resolve();
                return fetch(chunkUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': blob.type || 'audio/webm', 'Accept': 'application/json' },
                    body: blob,
                }).then(function (response) {
                    if (response.status === 409) {
                        setError('Direct terminé : l\'envoi de la capture a été arrêté.');
                        return window.__micCapture.stop();
                    } else if (response.status === 422) {
                        return response.json().then(function (body) {
                            setError((body && body.message) ? body.message : 'Chunk refusé par le serveur.');
                            return window.__micCapture.stop();
                        });
                    }
                });
            }

            window.__micCapture = {
                start: function () {
                    if (recorder || stopping) return;

                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        setError('Le micro exige une connexion sécurisée (HTTPS) ou localhost.');
                        return;
                    }

                    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
                        stream = s;
                        var mimeType = pickMimeType();
                        var options = { audioBitsPerSecond: 128000 };
                        if (mimeType) options.mimeType = mimeType;

                        try {
                            recorder = new MediaRecorder(stream, options);
                        } catch (e) {
                            stopTracks();
                            setError('Enregistrement audio non supporté par ce navigateur.');
                            return;
                        }

                        recorder.ondataavailable = function (event) { enqueueChunk(event.data); };
                        recorder.onstop = stopTracks;
                        recorder.start(1000);

                        setRunning(true);
                        setStatus('Micro actif — diffusion en cours');
                        setError('');
                    }).catch(function (error) {
                        if (error && error.name === 'NotAllowedError') {
                            setError('Accès au micro refusé : autorisez le micro dans le navigateur puis réessayez.');
                        } else if (error && (error.name === 'NotFoundError' || error.name === 'OverconstrainedError')) {
                            setError('Aucun microphone détecté sur cet appareil.');
                        } else if (error && error.name === 'NotReadableError') {
                            setError('Micro déjà utilisé par une autre application.');
                        } else {
                            setError('Accès au micro impossible (' + (error?.name || 'inconnu') + ').');
                        }
                    });
                },
                stop: function () {
                    if (stopping) return Promise.resolve();
                    stopping = true;

                    if (recorder && recorder.state !== 'inactive') {
                        recorder.stop();
                    }
                    stopTracks();
                    setRunning(false);
                    setStatus('Micro arrêté');

                    return fetch(stopUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    }).catch(function () {}).then(function () {
                        recorder = null;
                        stopping = false;
                    });
                },
            };

            window.addEventListener('beforeunload', function () {
                if (recorder && recorder.state !== 'inactive') recorder.stop();
                stopTracks();
            });

            var startBtn = byId('mic-start');
            var stopBtn = byId('mic-stop');
            if (startBtn) startBtn.addEventListener('click', function () { window.__micCapture && window.__micCapture.start(); });
            if (stopBtn) stopBtn.addEventListener('click', function () { window.__micCapture && window.__micCapture.stop(); });
        })();
        </script>
    @endif
</section>
