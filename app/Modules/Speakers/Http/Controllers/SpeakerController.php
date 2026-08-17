<?php

namespace App\Modules\Speakers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Support\ContentPresenter;
use App\Modules\Speakers\Http\Requests\StoreSpeakerRequest;
use App\Modules\Speakers\Http\Requests\UpdateSpeakerRequest;
use App\Modules\Speakers\Models\Speaker;
use App\Modules\Speakers\Services\SpeakerService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class SpeakerController extends Controller
{
    public function __construct(private readonly SpeakerService $speakers) {}

    /**
     * Liste publique des speakers actifs.
     */
    public function index(): JsonResponse
    {
        $speakers = Speaker::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Speaker $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'title' => $s->title,
                'bio' => $s->bio,
                'photo_path' => $s->photo_path,
            ]);

        return ApiResponse::success(['speakers' => $speakers]);
    }

    /**
     * Détail public d'un speaker + ses contenus publiés.
     */
    public function show(Speaker $speaker): JsonResponse
    {
        $contents = $speaker->contents()
            ->where('status', 'published')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($content) => ContentPresenter::payload($content));

        return ApiResponse::success([
            'speaker' => [
                'id' => $speaker->id,
                'name' => $speaker->name,
                'title' => $speaker->title,
                'bio' => $speaker->bio,
                'photo_path' => $speaker->photo_path,
                'contents' => $contents,
            ],
        ]);
    }

    /**
     * Création (admin, speaker.create).
     */
    public function store(StoreSpeakerRequest $request): JsonResponse
    {
        $this->authorize('create', Speaker::class);

        $speaker = $this->speakers->create($request->validated());

        return ApiResponse::success(['speaker' => $speaker], status: 201);
    }

    /**
     * Modification (admin, speaker.update).
     */
    public function update(Speaker $speaker, UpdateSpeakerRequest $request): JsonResponse
    {
        $this->authorize('update', $speaker);

        $speaker = $this->speakers->update($speaker, $request->validated());

        return ApiResponse::success(['speaker' => $speaker]);
    }

    /**
     * Suppression (admin, speaker.delete).
     */
    public function destroy(Speaker $speaker): JsonResponse
    {
        $this->authorize('delete', $speaker);

        if (! $this->speakers->delete($speaker)) {
            return ApiResponse::error('speaker_in_use', 'Ce conférencier est encore utilisé et ne peut pas être supprimé.', 422);
        }

        return ApiResponse::success(['message' => 'Conférencier supprimé.']);
    }
}
