<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreProgramRequest;
use App\Modules\Organization\Http\Requests\UpdateProgramRequest;
use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Services\ProgramService;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgramController extends Controller
{
    public function __construct(private readonly ProgramService $programs) {}

    public function index(Week $week): JsonResponse
    {
        $this->authorize('viewAny', Program::class);

        return ApiResponse::success([
            'programs' => $week->programs()->orderBy('day_of_week')->orderBy('start_time')->get(),
        ]);
    }

    public function store(Week $week, StoreProgramRequest $request): JsonResponse
    {
        $this->authorize('create', Program::class);

        $data = [
            'day_of_week' => (int) $request->integer('day_of_week'),
            'start_time' => (string) $request->string('start_time'),
            'duration_minutes' => (int) $request->integer('duration_minutes'),
            'type' => (string) $request->string('type'),
        ];

        $program = $this->programs->create($week, $data);

        return ApiResponse::success(['program' => $program], status: 201);
    }

    public function update(Week $week, Program $program, UpdateProgramRequest $request): JsonResponse
    {
        $this->authorize('update', $program);

        $data = [
            'day_of_week' => (int) $request->integer('day_of_week'),
            'start_time' => (string) $request->string('start_time'),
            'duration_minutes' => (int) $request->integer('duration_minutes'),
            'type' => (string) $request->string('type'),
        ];

        $program = $this->programs->update($program, $data);

        return ApiResponse::success(['program' => $program]);
    }

    public function destroy(Week $week, Program $program): JsonResponse
    {
        $this->authorize('delete', $program);

        if (! $this->programs->delete($program)) {
            return ApiResponse::error('program_in_use', 'Ce programme est encore utilisé et ne peut pas être supprimé.', 422);
        }

        return ApiResponse::success(['message' => 'Programme supprimé.']);
    }
}
