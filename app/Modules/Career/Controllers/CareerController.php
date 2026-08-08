<?php

namespace App\Modules\Career\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Career\Enums\CareerStatus;
use App\Modules\Career\Requests\{StoreCareerRequest, UpdateCareerRequest};
use App\Modules\Career\Resources\CareerResource;
use App\Modules\Career\Services\CareerService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CareerController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CareerService $careerService,
    ) {}

    // ── Public endpoints ──────────────────────────────────────────────────────

    // GET /api/v1/careers
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search'          => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', 'string'],
            'location'        => ['nullable', 'string', 'max:100'],
            'sort'            => ['nullable', 'string', 'in:latest,oldest,title_asc,title_desc'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $careers = $this->careerService->listPublished(
            filters: $request->only(['search', 'employment_type', 'location', 'sort']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->success(
            CareerResource::collection($careers)->response()->getData(true)
        );
    }

    // GET /api/v1/careers/{slug}
    public function show(string $slug): JsonResponse
    {
        $career = $this->careerService->findBySlug($slug);

        return $this->success(new CareerResource($career));
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    // GET /api/v1/admin/careers
    public function adminIndex(Request $request): JsonResponse
    {
        $request->validate([
            'search'          => ['nullable', 'string', 'max:100'],
            'status'          => ['nullable', 'string'],
            'employment_type' => ['nullable', 'string'],
            'sort'            => ['nullable', 'string', 'in:latest,oldest,title_asc,title_desc'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $careers = $this->careerService->listAdmin(
            filters: $request->only(['search', 'status', 'employment_type', 'sort']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->success(
            CareerResource::collection($careers)->response()->getData(true)
        );
    }

    // GET /api/v1/admin/careers/{career}
    public function adminShow(string $careerId): JsonResponse
    {
        $career = $this->careerService->findById($careerId);

        return $this->success(new CareerResource($career));
    }

    // POST /api/v1/admin/careers
    public function store(StoreCareerRequest $request): JsonResponse
    {
        $career = $this->careerService->create($request->validated());

        return $this->created(new CareerResource($career), 'Lowongan berhasil dibuat.');
    }

    // PUT /api/v1/admin/careers/{career}
    public function update(UpdateCareerRequest $request, string $careerId): JsonResponse
    {
        $career = $this->careerService->update($careerId, $request->validated());

        return $this->success(new CareerResource($career), 'Lowongan berhasil diperbarui.');
    }

    // DELETE /api/v1/admin/careers/{career}
    public function destroy(string $careerId): JsonResponse
    {
        $this->careerService->delete($careerId);

        return $this->noContent();
    }

    // PATCH /api/v1/admin/careers/{career}/status
    public function updateStatus(Request $request, string $careerId): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(CareerStatus::class)],
        ]);

        $career = $this->careerService->updateStatus($careerId, $request->status);

        return $this->success(
            new CareerResource($career),
            'Status lowongan berhasil diperbarui.'
        );
    }
}
