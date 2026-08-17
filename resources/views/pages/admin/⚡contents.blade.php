<?php

use App\Modules\Content\Exceptions\InvalidContentTransitionException;
use App\Modules\Content\Models\Content;
use App\Modules\Content\Services\ContentService;
use App\Modules\Content\Storage\AudioStorage;
use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Speakers\Models\Speaker;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Settings\SettingsService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Contenus audio')] class extends Component
{
    use WithFileUploads;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public $audioFile;

    public $imageFile;

    public string $createTitle = '';

    public string $createDescription = '';

    public string $createSpeaker = '';

    public ?int $createSpeakerId = null;

    public ?int $createYearId = null;

    public ?int $createMonthId = null;

    public ?int $createWeekId = null;

    public ?int $createActivityId = null;

    public ?int $createDayOfWeek = null;

    public ?int $editId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editSpeaker = '';

    public ?int $editSpeakerId = null;

    public ?int $editDuration = null;

    public ?int $editYearId = null;

    public ?int $editMonthId = null;

    public ?int $editWeekId = null;

    public ?int $editActivityId = null;

    public ?int $editDayOfWeek = null;

    public $editAudioFile;

    public $editImageFile;

    public ?int $scheduleTarget = null;

    public string $scheduleAt = '';

    public ?int $deleteTarget = null;

    public function mount(): void
    {
        $this->createYearId = Year::query()->where('is_current', true)->value('id');
    }

    public function refreshContentList(): void
    {
        // Called by wire:poll to re-render with fresh data.
    }

    public function getRowsProperty(): LengthAwarePaginator
    {
        return Content::query()
            ->with(['year:id,label', 'week:id,label', 'specialActivity:id,name', 'speakerRel:id,name,title'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query): void {
                    $query->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%")
                        ->orWhere('speaker', 'like', "%{$this->search}%")
                        ->orWhereHas('speakerRel', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(12);
    }

    public function getCreateYearsProperty(): array
    {
        return Year::query()->orderBy('label')->get()->map(fn (Year $year): array => ['id' => $year->id, 'label' => $year->label])->all();
    }

    public function getCreateMonthsProperty(): array
    {
        if ($this->createYearId === null) {
            return [];
        }

        return Month::query()->where('year_id', $this->createYearId)->orderBy('month_number')
            ->get()->map(fn (Month $month): array => [
                'id' => $month->id,
                'label' => Month::NAMES[$month->month_number] ?? (string) $month->month_number,
            ])->all();
    }

    public function getCreateWeeksProperty(): array
    {
        if ($this->createYearId === null) {
            return [];
        }

        return Week::query()->where('year_id', $this->createYearId)->orderBy('label')
            ->get()->map(fn (Week $week): array => ['id' => $week->id, 'label' => $week->label])->all();
    }

    public function getCreateActivitiesProperty(): array
    {
        if ($this->createWeekId === null) {
            return [];
        }

        return SpecialActivity::query()->with('activityType')->where('week_id', $this->createWeekId)->orderByDesc('id')
            ->get()->map(fn (SpecialActivity $activity): array => [
                'id' => $activity->id,
                'label' => $activity->name.' ('.$activity->activityType->label.')',
            ])->all();
    }

    public function getEditYearsProperty(): array
    {
        return Year::query()->orderBy('label')->get()->map(fn (Year $year): array => ['id' => $year->id, 'label' => $year->label])->all();
    }

    public function getEditMonthsProperty(): array
    {
        if ($this->editYearId === null) {
            return [];
        }

        return Month::query()->where('year_id', $this->editYearId)->orderBy('month_number')
            ->get()->map(fn (Month $month): array => [
                'id' => $month->id,
                'label' => Month::NAMES[$month->month_number] ?? (string) $month->month_number,
            ])->all();
    }

    public function getEditWeeksProperty(): array
    {
        if ($this->editYearId === null) {
            return [];
        }

        return Week::query()->where('year_id', $this->editYearId)->orderBy('label')
            ->get()->map(fn (Week $week): array => ['id' => $week->id, 'label' => $week->label])->all();
    }

    public function getEditActivitiesProperty(): array
    {
        if ($this->editWeekId === null) {
            return [];
        }

        return SpecialActivity::query()->with('activityType')->where('week_id', $this->editWeekId)->orderByDesc('id')
            ->get()->map(fn (SpecialActivity $activity): array => [
                'id' => $activity->id,
                'label' => $activity->name.' ('.$activity->activityType->label.')',
            ])->all();
    }

    public function getCreateWeekProgramsProperty(): array
    {
        if ($this->createWeekId === null) {
            return [];
        }

        return Program::query()
            ->where('week_id', $this->createWeekId)
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (Program $p): array => [
                'id' => $p->day_of_week,
                'label' => (Content::DAYS[$p->day_of_week] ?? (string) $p->day_of_week)
                    .($p->type !== '' ? ' — '.$p->type : ''),
                'type' => $p->type,
            ])
            ->all();
    }

    public function getEditWeekProgramsProperty(): array
    {
        if ($this->editWeekId === null) {
            return [];
        }

        return Program::query()
            ->where('week_id', $this->editWeekId)
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (Program $p): array => [
                'id' => $p->day_of_week,
                'label' => (Content::DAYS[$p->day_of_week] ?? (string) $p->day_of_week)
                    .($p->type !== '' ? ' — '.$p->type : ''),
                'type' => $p->type,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function getSpeakersProperty(): array
    {
        return Speaker::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Speaker $s): array => [
                'id' => $s->id,
                'label' => $s->label(),
            ])
            ->all();
    }

    public function updatedCreateYearId(): void
    {
        $this->createMonthId = null;
        $this->createWeekId = null;
        $this->createActivityId = null;
    }

    public function updatedCreateWeekId(): void
    {
        $this->createActivityId = null;
    }

    public function updatedEditYearId(): void
    {
        $this->editMonthId = null;
        $this->editWeekId = null;
        $this->editActivityId = null;
    }

    public function updatedEditWeekId(): void
    {
        $this->editActivityId = null;
    }

    public function createContent(): void
    {
        $this->validate([
            'audioFile' => ['required', 'file', 'extensions:'.implode(',', AudioStorage::allowedExtensions()), 'max:'.$this->maxUploadKb()],
            'imageFile' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'createTitle' => ['required', 'string', 'max:255'],
            'createDescription' => ['nullable', 'string', 'max:5000'],
            'createSpeaker' => ['nullable', 'string', 'max:255'],
            'createSpeakerId' => ['nullable', 'integer', 'exists:speakers,id'],
            'createYearId' => ['nullable', 'integer', 'exists:years,id'],
            'createMonthId' => ['nullable', 'integer', 'exists:months,id'],
            'createWeekId' => ['nullable', 'integer', 'exists:weeks,id'],
            'createActivityId' => ['nullable', 'integer', 'exists:special_activities,id'],
            'createDayOfWeek' => ['nullable', 'integer', 'between:1,7'],
        ]);

        $this->ensureOrgConsistency(
            'createYearId', $this->createYearId,
            'createMonthId', $this->createMonthId,
            'createWeekId', $this->createWeekId,
            'createActivityId', $this->createActivityId,
        );

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        app(ContentService::class)->create($this->audioFile, [
            'title' => trim($this->createTitle),
            'description' => $this->createDescription !== '' ? trim($this->createDescription) : null,
            'speaker' => $this->createSpeakerId !== null ? null : ($this->createSpeaker !== '' ? trim($this->createSpeaker) : null),
            'speaker_id' => $this->createSpeakerId,
            'year_id' => $this->createYearId,
            'month_id' => $this->createMonthId,
            'week_id' => $this->createWeekId,
            'special_activity_id' => $this->createActivityId,
            'day_of_week' => $this->createDayOfWeek,
        ], $this->imageFile);

        $this->reset('audioFile', 'imageFile', 'createTitle', 'createDescription', 'createSpeaker', 'createSpeakerId', 'createYearId', 'createMonthId', 'createWeekId', 'createActivityId', 'createDayOfWeek');
        $this->createYearId = Year::query()->where('is_current', true)->value('id');

        Flux::toast(variant: 'success', text: __('Contenu créé.'));
    }

    public function startEdit(int $id): void
    {
        $content = Content::findOrFail($id);

        abort_unless(auth()->user()->can('update', $content), 403);

        $this->editId = $content->id;
        $this->editTitle = $content->title;
        $this->editDescription = $content->description ?? '';
        $this->editSpeaker = $content->speaker ?? '';
        $this->editSpeakerId = $content->speaker_id;
        $this->editDuration = $content->duration_seconds;
        $this->editYearId = $content->year_id;
        $this->editMonthId = $content->month_id;
        $this->editWeekId = $content->week_id;
        $this->editActivityId = $content->special_activity_id;
        $this->editDayOfWeek = $content->day_of_week;

        $this->modal('edit-content')->show();
    }

    public function updateContent(): void
    {
        $content = Content::findOrFail($this->editId);

        abort_unless(auth()->user()->can('update', $content), 403);

        $this->validate([
            'editAudioFile' => ['nullable', 'file', 'extensions:'.implode(',', AudioStorage::allowedExtensions()), 'max:'.$this->maxUploadKb()],
            'editImageFile' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'editTitle' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string', 'max:5000'],
            'editSpeaker' => ['nullable', 'string', 'max:255'],
            'editSpeakerId' => ['nullable', 'integer', 'exists:speakers,id'],
            'editDuration' => ['nullable', 'integer', 'min:0'],
            'editYearId' => ['nullable', 'integer', 'exists:years,id'],
            'editMonthId' => ['nullable', 'integer', 'exists:months,id'],
            'editWeekId' => ['nullable', 'integer', 'exists:weeks,id'],
            'editActivityId' => ['nullable', 'integer', 'exists:special_activities,id'],
            'editDayOfWeek' => ['nullable', 'integer', 'between:1,7'],
        ]);

        $this->ensureOrgConsistency(
            'editYearId', $this->editYearId,
            'editMonthId', $this->editMonthId,
            'editWeekId', $this->editWeekId,
            'editActivityId', $this->editActivityId,
        );

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        app(ContentService::class)->update($content, [
            'title' => trim($this->editTitle),
            'description' => $this->editDescription !== '' ? trim($this->editDescription) : null,
            'speaker' => $this->editSpeakerId !== null ? null : ($this->editSpeaker !== '' ? trim($this->editSpeaker) : null),
            'speaker_id' => $this->editSpeakerId,
            'duration_seconds' => $this->editDuration,
            'year_id' => $this->editYearId,
            'month_id' => $this->editMonthId,
            'week_id' => $this->editWeekId,
            'special_activity_id' => $this->editActivityId,
            'day_of_week' => $this->editDayOfWeek,
            'sort_order' => $content->sort_order,
        ], $this->editAudioFile, $this->editImageFile);

        $this->editId = null;
        $this->reset('editAudioFile', 'editImageFile');
        $this->modal('edit-content')->close();

        Flux::toast(variant: 'success', text: __('Contenu mis à jour.'));
    }

    public function changeStatus(int $id, string $to): void
    {
        $content = Content::findOrFail($id);

        abort_unless(auth()->user()->can('publish', $content), 403);

        if (! in_array($to, Content::allowedTransitions($content->status), true)) {
            return;
        }

        if ($to === Content::STATUS_SCHEDULED) {
            $this->scheduleTarget = $content->id;
            $this->scheduleAt = '';
            $this->modal('schedule-content')->show();

            return;
        }

        $this->applyTransition($content, $to);
    }

    public function confirmSchedule(): void
    {
        $this->validate([
            'scheduleAt' => ['required', 'date', 'after:now'],
        ]);

        $content = Content::findOrFail($this->scheduleTarget);

        abort_unless(auth()->user()->can('publish', $content), 403);

        $this->applyTransition($content, Content::STATUS_SCHEDULED, Carbon::parse($this->scheduleAt));

        $this->scheduleTarget = null;
        $this->scheduleAt = '';
        $this->modal('schedule-content')->close();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteTarget = $id;

        $this->modal('delete-content')->show();
    }

    public function deleteContent(): void
    {
        $content = Content::findOrFail($this->deleteTarget);

        abort_unless(auth()->user()->can('delete', $content), 403);

        app(ContentService::class)->delete($content);

        $this->deleteTarget = null;
        $this->modal('delete-content')->close();

        Flux::toast(variant: 'success', text: __('Contenu supprimé.'));
    }

    public function maxUploadKb(): int
    {
        return (int) app(SettingsService::class)->get('audio_max_upload_mb') * 1024;
    }

    public function canPublish(Content $content): bool
    {
        return auth()->user()->can('publish', $content);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            Content::STATUS_DRAFT => __('Brouillon'),
            Content::STATUS_SCHEDULED => __('Programmé'),
            Content::STATUS_PUBLISHED => __('Publié'),
            Content::STATUS_UNPUBLISHED => __('Dépublié'),
            Content::STATUS_ARCHIVED => __('Archivé'),
            default => $status,
        };
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            Content::STATUS_PUBLISHED => 'green',
            Content::STATUS_SCHEDULED => 'amber',
            Content::STATUS_UNPUBLISHED => 'red',
            Content::STATUS_ARCHIVED => 'gray',
            default => 'gray',
        };
    }

    public function durationLabel(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return "{$seconds} s";
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $rest > 0 ? "{$minutes} min {$rest} s" : "{$minutes} min";
    }

    public function bytesLabel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' Mo';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' Ko';
        }

        return "{$bytes} o";
    }

    public function contextLabel(Content $content): string
    {
        $parts = [];

        if ($content->day_of_week !== null) {
            $parts[] = Content::DAYS[$content->day_of_week] ?? (string) $content->day_of_week;
        }

        if ($content->year !== null) {
            $parts[] = $content->year->label;
        }

        if ($content->week !== null) {
            $parts[] = $content->week->label;
        }

        if ($content->specialActivity !== null) {
            $parts[] = $content->specialActivity->name;
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /**
     * Règle A2 : cohérence du rattachement à l'organisation.
     */
    private function ensureOrgConsistency(
        string $yearKey,
        ?int $yearId,
        string $monthKey,
        ?int $monthId,
        string $weekKey,
        ?int $weekId,
        string $activityKey,
        ?int $activityId,
    ): void {
        if ($monthId !== null && ($yearId === null || ! Month::whereKey($monthId)->where('year_id', $yearId)->exists())) {
            $this->addError($monthKey, __('Ce mois n\'appartient pas à l\'année sélectionnée.'));
        }

        if ($weekId !== null && ($yearId === null || ! Week::whereKey($weekId)->where('year_id', $yearId)->exists())) {
            $this->addError($weekKey, __('Cette semaine n\'appartient pas à l\'année sélectionnée.'));
        }

        if ($activityId !== null) {
            $activity = SpecialActivity::find($activityId);

            if ($activity === null || $weekId === null || $activity->week_id !== $weekId) {
                $this->addError($activityKey, __('Cette activité ne correspond pas à la semaine sélectionnée.'));
            }
        }
    }

    private function applyTransition(Content $content, string $to, ?Carbon $scheduledAt = null): void
    {
        try {
            app(ContentService::class)->transition($content, $to, $scheduledAt);
        } catch (InvalidContentTransitionException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Statut mis à jour.'));
    }
}; ?>

<section class="w-full" wire:poll.10s="refreshContentList">
    @php($rows = $this->rows)

    <div>
        <flux:heading size="xl" level="1">{{ __('Contenus audio') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Gestion complète des contenus : upload, métadonnées, publication.') }}</flux:text>
    </div>

    <form wire:submit="createContent" class="my-6">
        <flux:card>
            <flux:heading>{{ __('Nouveau contenu') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Choisissez le fichier audio, le titre, puis la semaine et le jour programé.') }}
            </flux:text>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <flux:field class="lg:col-span-1">
                    <flux:label>{{ __('Fichier audio') }}</flux:label>
                    <input type="file" wire:model="audioFile" accept=".mp3,.m4a,.wav,.ogg,.aac"
                        class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                    <flux:error name="audioFile" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Image de couverture') }}</flux:label>
                    <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                    <flux:error name="imageFile" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Titre') }}</flux:label>
                    <flux:input wire:model="createTitle" required />
                    <flux:error name="createTitle" />
                </flux:field>

                <flux:field class="lg:col-span-1">
                    <flux:label>{{ __('Conférencier') }}</flux:label>
                    <flux:select wire:model="createSpeakerId">
                        <option value="">—</option>
                        @foreach ($this->speakers as $speaker)
                            <option value="{{ $speaker['id'] }}">{{ $speaker['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="createSpeakerId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Année') }}</flux:label>
                    <flux:select wire:model="createYearId">
                        <option value="">—</option>
                        @foreach ($this->createYears as $year)
                            <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="createYearId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Mois') }}</flux:label>
                    <flux:select wire:model="createMonthId">
                        <option value="">—</option>
                        @foreach ($this->createMonths as $month)
                            <option value="{{ $month['id'] }}">{{ $month['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="createMonthId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Semaine') }}</flux:label>
                    <flux:select wire:model.live="createWeekId">
                        <option value="">—</option>
                        @foreach ($this->createWeeks as $week)
                            <option value="{{ $week['id'] }}">{{ $week['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="createWeekId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Jour de la semaine') }}</flux:label>
                    @if (count($this->createWeekPrograms) > 0)
                        <flux:select wire:model.live="createDayOfWeek">
                            <option value="">—</option>
                            @foreach ($this->createWeekPrograms as $program)
                                <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:text size="sm" class="mt-1">{{ __('Aucun programme défini pour cette semaine.') }}</flux:text>
                    @endif
                    <flux:error name="createDayOfWeek" />
                </flux:field>

                <flux:field class="lg:col-span-3">
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="createDescription" rows="3" />
                    <flux:error name="createDescription" />
                </flux:field>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <flux:button variant="primary" type="submit">{{ __('Créer le contenu') }}</flux:button>
            </div>
        </flux:card>
    </form>

    <flux:card>
        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher…')" class="sm:max-w-sm" />

            <flux:select wire:model.live="statusFilter" class="sm:max-w-xs">
                <option value="">— {{ __('Tous les statuts') }} —</option>
                @foreach (\App\Modules\Content\Models\Content::statuses() as $status)
                    <option value="{{ $status }}">{{ $this->statusLabel($status) }}</option>
                @endforeach
            </flux:select>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Titre') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column>{{ __('Contexte') }}</flux:table.column>
                <flux:table.column>{{ __('Durée') }}</flux:table.column>
                <flux:table.column>{{ __('Taille') }}</flux:table.column>
                <flux:table.column class="w-72" />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rows as $content)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $content->title }}</div>
                            @if ($content->speakerRel)
                                <div class="text-sm text-zinc-400">{{ $content->speakerRel->label() }}</div>
                            @elseif ($content->speaker)
                                <div class="text-sm text-zinc-400">{{ $content->speaker }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$this->statusColor($content->status)" size="sm">
                                {{ $this->statusLabel($content->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">{{ $this->contextLabel($content) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->durationLabel($content->duration_seconds) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->bytesLabel($content->size_bytes) }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if ($this->canPublish($content) && \App\Modules\Content\Models\Content::allowedTransitions($content->status) !== [])
                                    <flux:select size="xs" wire:change="changeStatus({{ $content->id }}, $event.target.value)">
                                        <option value="">— {{ __('Statut') }} —</option>
                                        @foreach (\App\Modules\Content\Models\Content::allowedTransitions($content->status) as $target)
                                            <option value="{{ $target }}">{{ $this->statusLabel($target) }}</option>
                                        @endforeach
                                    </flux:select>
                                @endif

                                <flux:button size="xs" variant="subtle" wire:click="startEdit({{ $content->id }})">
                                    {{ __('Modifier') }}
                                </flux:button>

                                <flux:button variant="danger" size="xs" wire:click="confirmDelete({{ $content->id }})">
                                    {{ __('Supprimer') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400">
                            {{ __('Aucun contenu.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="p-4">
            {{ $rows->links() }}
        </div>
    </flux:card>

    <flux:modal name="edit-content" :dismissible="false" class="w-full max-w-3xl">
        <form wire:submit="updateContent">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modifier le contenu') }}</flux:heading>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Titre') }}</flux:label>
                        <flux:input wire:model="editTitle" required />
                        <flux:error name="editTitle" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Conférencier') }}</flux:label>
                        <flux:select wire:model="editSpeakerId">
                            <option value="">—</option>
                            @foreach ($this->speakers as $speaker)
                                <option value="{{ $speaker['id'] }}">{{ $speaker['label'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editSpeakerId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Durée (secondes)') }}</flux:label>
                        <flux:input type="number" wire:model="editDuration" min="0" />
                        <flux:error name="editDuration" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Année') }}</flux:label>
                        <flux:select wire:model="editYearId">
                            <option value="">—</option>
                            @foreach ($this->editYears as $year)
                                <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editYearId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Mois') }}</flux:label>
                        <flux:select wire:model="editMonthId">
                            <option value="">—</option>
                            @foreach ($this->editMonths as $month)
                                <option value="{{ $month['id'] }}">{{ $month['label'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editMonthId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Semaine') }}</flux:label>
                        <flux:select wire:model.live="editWeekId">
                            <option value="">—</option>
                            @foreach ($this->editWeeks as $week)
                                <option value="{{ $week['id'] }}">{{ $week['label'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editWeekId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Jour de la semaine') }}</flux:label>
                        @if (count($this->editWeekPrograms) > 0)
                            <flux:select wire:model.live="editDayOfWeek">
                                <option value="">—</option>
                                @foreach ($this->editWeekPrograms as $program)
                                    <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
                                @endforeach
                            </flux:select>
                        @else
                            <flux:text size="sm" class="mt-1">{{ __('Aucun programme défini pour cette semaine.') }}</flux:text>
                        @endif
                        <flux:error name="editDayOfWeek" />
                    </flux:field>

                    <flux:field class="lg:col-span-2">
                        <flux:label>{{ __('Remplacer le fichier audio') }}</flux:label>
                        <input type="file" wire:model="editAudioFile" accept=".mp3,.m4a,.wav,.ogg,.aac"
                            class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                        <flux:error name="editAudioFile" />
                    </flux:field>

                    <flux:field class="lg:col-span-2">
                        <flux:label>{{ __('Remplacer l\'image de couverture') }}</flux:label>
                        <input type="file" wire:model="editImageFile" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                        <flux:error name="editImageFile" />
                    </flux:field>

                    <flux:field class="lg:col-span-2">
                        <flux:label>{{ __('Description') }}</flux:label>
                        <flux:textarea wire:model="editDescription" rows="3" />
                        <flux:error name="editDescription" />
                    </flux:field>
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

    <flux:modal name="schedule-content" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Programmer la publication') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Le contenu sera publié automatiquement à la date indiquée.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Date de publication') }}</flux:label>
                <flux:input type="datetime-local" wire:model="scheduleAt" />
                <flux:error name="scheduleAt" />
            </flux:field>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('scheduleTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="primary" wire:click="confirmSchedule">
                    {{ __('Programmer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="delete-content" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Supprimer ce contenu ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Le fichier audio et l\'image seront définitivement supprimés.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('deleteTarget', null)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteContent">
                    {{ __('Supprimer') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
