<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Exceptions\InvalidContentTransitionException;
use App\Modules\Content\Http\Requests\ContentIndexRequest;
use App\Modules\Content\Http\Requests\StoreContentRequest;
use App\Modules\Content\Http\Requests\UpdateContentRequest;
use App\Modules\Content\Http\Requests\UpdateStatusRequest;
use App\Modules\Content\Http\Requests\UploadContentRequest;
use App\Modules\Content\Models\Content;
use App\Modules\Content\Services\ContentService;
use App\Modules\Content\Support\AudioStream;
use App\Modules\Content\Support\ContentPresenter;
use App\Modules\Content\Support\ImageStream;
use App\Shared\Api\ApiResponse;
use App\Shared\Support\PaginatedCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContentController extends Controller
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly ContentService $contents,
        private readonly AudioStream $stream,
        private readonly ImageStream $images,
    ) {}

    public function upload(UploadContentRequest $request): JsonResponse
    {
        $this->authorize('create', Content::class);

        $file = $request->file('file');

        if ($file === null) {
            return ApiResponse::error('validation_error', 'Le fichier est requis.', 422);
        }

        $content = $this->contents->upload(
            $file,
            [
                'title' => $request->input('title'),
                'description' => $request->input('description'),
            ],
        );

        return ApiResponse::success(['content' => $content], status: 201);
    }

    public function store(StoreContentRequest $request): JsonResponse
    {
        $this->authorize('create', Content::class);

        $content = $this->contents->create($request->file('file'), $this->contentData($request), $request->file('image'));

        return ApiResponse::success(['content' => $this->payload($content)], status: 201);
    }

    public function update(Content $content, UpdateContentRequest $request): JsonResponse
    {
        $this->authorize('update', $content);

        $content = $this->contents->update($content, $this->contentData($request), $request->file('file'), $request->file('image'));

        return ApiResponse::success(['content' => $this->payload($content)]);
    }

    public function status(Content $content, UpdateStatusRequest $request): JsonResponse
    {
        $this->authorize('publish', $content);

        try {
            $content = $this->contents->transition($content, (string) $request->string('status'), $request->date('scheduled_at'));
        } catch (InvalidContentTransitionException $exception) {
            return ApiResponse::error('invalid_transition', $exception->getMessage(), 422);
        }

        return ApiResponse::success(['content' => $this->payload($content)]);
    }

    public function destroy(Content $content): JsonResponse
    {
        $this->authorize('delete', $content);

        $this->contents->delete($content);

        return ApiResponse::success(['message' => 'Contenu supprimé.']);
    }

    /**
     * Index public (US-024, US-027) : seuls les contenus publiés (US-025),
     * filtres par année/mois/semaine/activité, paginé, cache TTL 300 versionné
     * (chaque transition de statut incrémente la version). Ordre de l'accueil :
     * `sort_order` croissant (ordre métier, défaut 0) puis `id` décroissant
     * (récence d'insertion).
     */
    public function index(ContentIndexRequest $request): JsonResponse
    {
        $filters = [
            'year' => $request->integer('year'),
            'month' => $request->integer('month'),
            'week' => $request->integer('week'),
            'activity' => $request->integer('activity'),
        ];

        $search = trim((string) $request->string('search'));
        $page = (int) $request->integer('page', 1);

        $build = function (int $page) use ($filters, $search): LengthAwarePaginator {
            $query = Content::query()
                ->where('status', Content::STATUS_PUBLISHED)
                ->with(self::relations());

            $columns = [
                'year' => 'year_id',
                'month' => 'month_id',
                'week' => 'week_id',
                'activity' => 'special_activity_id',
            ];

            foreach ($columns as $filter => $column) {
                if ($filters[$filter]) {
                    $query->where($column, $filters[$filter]);
                }
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $q->whereLike('title', "%{$search}%")
                        ->orWhereLike('description', "%{$search}%")
                        ->orWhereLike('speaker', "%{$search}%");
                });
            }

            return $query->orderBy('sort_order')->orderByDesc('id')->paginate(10, ['*'], 'page', $page);
        };

        $builder = fn (): LengthAwarePaginator => $build($page);
        $transform = fn (Content $content): array => $this->payload($content);

        if ($search !== '') {
            $paginator = PaginatedCache::make($builder, $transform);
        } else {
            $version = (int) Cache::get(ContentService::PUBLIC_CACHE_VERSION_KEY, 0);

            $paginator = PaginatedCache::remember(
                'public.contents.v'.$version.'.'.implode('.', $filters).'.page.'.$page,
                self::CACHE_TTL,
                $builder,
                $transform,
            );
        }

        $response = ApiResponse::paginate($paginator);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }

    public function show(Content $content): JsonResponse
    {
        if (! $content->isPublished()) {
            throw new NotFoundHttpException('Ce contenu n\'est pas publié.');
        }

        $version = (int) Cache::get(ContentService::PUBLIC_CACHE_VERSION_KEY, 0);

        $content = Cache::remember(
            "public.contents.v{$version}.show.{$content->id}",
            self::CACHE_TTL,
            fn () => $content->load(self::relations()),
        );

        $response = ApiResponse::success(['content' => $this->payload($content)]);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }

    /**
     * Lecture du flux (public) : uniquement les contenus publiés (US-025, A2).
     */
    public function stream(Content $content): BinaryFileResponse
    {
        if (! $content->isPublished()) {
            throw new NotFoundHttpException('Ce contenu n\'est pas publié.');
        }

        return $this->stream->serve($content);
    }

    /**
     * Visuel du contenu (public, contenu publié uniquement). 404 si aucun
     * visuel ou fichier absent.
     */
    public function image(Content $content): BinaryFileResponse
    {
        if (! $content->isPublished()) {
            throw new NotFoundHttpException('Ce contenu n\'est pas publié.');
        }

        return $this->images->serve($content);
    }

    /**
     * @return list<string>
     */
    private static function relations(): array
    {
        return ['year:id,label', 'month:id,month_number', 'week:id,label', 'specialActivity:id,name'];
    }

    /**
     * Forme normalisée d'un contenu pour l'API.
     *
     * @return array<string, mixed>
     */
    private function payload(Content $content): array
    {
        return ContentPresenter::payload($content);
    }

    /**
     * @return array{title: string, description: string|null, duration_seconds: int|null, speaker: string|null, year_id: int|null, month_id: int|null, week_id: int|null, special_activity_id: int|null, day_of_week: int|null, notes: string|null, approved_by: string|null, approval_comment: string|null, approved_at: string|null, scheduled_at: string|null, sort_order: int}
     */
    private function contentData(StoreContentRequest|UpdateContentRequest $request): array
    {
        return [
            'title' => (string) $request->string('title'),
            'description' => $request->filled('description') ? (string) $request->string('description') : null,
            'duration_seconds' => $request->filled('duration_seconds') ? (int) $request->integer('duration_seconds') : null,
            'speaker' => $request->filled('speaker') ? (string) $request->string('speaker') : null,
            'year_id' => $request->filled('year_id') ? (int) $request->integer('year_id') : null,
            'month_id' => $request->filled('month_id') ? (int) $request->integer('month_id') : null,
            'week_id' => $request->filled('week_id') ? (int) $request->integer('week_id') : null,
            'special_activity_id' => $request->filled('special_activity_id') ? (int) $request->integer('special_activity_id') : null,
            'day_of_week' => $request->filled('day_of_week') ? (int) $request->integer('day_of_week') : null,
            'notes' => $request->filled('notes') ? (string) $request->string('notes') : null,
            'approved_by' => $request->filled('approved_by') ? (string) $request->string('approved_by') : null,
            'approval_comment' => $request->filled('approval_comment') ? (string) $request->string('approval_comment') : null,
            'approved_at' => $request->filled('approved_at') ? (string) $request->string('approved_at') : null,
            'scheduled_at' => $request->filled('scheduled_at') ? (string) $request->string('scheduled_at') : null,
            'sort_order' => $request->filled('sort_order') ? (int) $request->integer('sort_order') : 0,
        ];
    }
}
