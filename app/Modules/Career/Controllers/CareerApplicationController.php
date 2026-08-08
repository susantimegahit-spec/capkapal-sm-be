<?php

namespace App\Modules\Career\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Career\Enums\ApplicationStatus;
use App\Modules\Career\Requests\SubmitCareerApplicationRequest;
use App\Modules\Career\Resources\CareerApplicationResource;
use App\Modules\Career\Services\{CareerApplicationService, CareerService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CareerApplicationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CareerApplicationService $applicationService,
        private readonly CareerService            $careerService,
    ) {}

    // ── Public endpoint ───────────────────────────────────────────────────────

    // POST /api/v1/careers/{slug}/apply
    public function store(SubmitCareerApplicationRequest $request, string $slug): JsonResponse
    {
        $career = $this->careerService->findBySlug($slug);

        $application = $this->applicationService->submit(
            career: $career,
            data  : $request->safe()->except('resume'),
            resume: $request->file('resume'),
        );

        return $this->created(
            new CareerApplicationResource($application),
            'Lamaran berhasil dikirim.'
        );
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    // GET /api/v1/admin/careers/{career}/applications
    public function index(Request $request, string $careerId): JsonResponse
    {
        $request->validate([
            'status'   => ['nullable', 'string'],
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $applications = $this->applicationService->listByCareer(
            careerId: $careerId,
            filters : $request->only(['status', 'search']),
            perPage : $request->integer('per_page', 20),
        );

        return $this->success(
            CareerApplicationResource::collection($applications)->response()->getData(true)
        );
    }

    // GET /api/v1/admin/careers/applications/{application}
    public function show(string $applicationId): JsonResponse
    {
        $application = $this->applicationService->findById($applicationId);

        return $this->success(new CareerApplicationResource($application));
    }

    // PATCH /api/v1/admin/careers/applications/{application}/status
    public function updateStatus(Request $request, string $applicationId): JsonResponse
    {
        $request->validate([
            'status'      => ['required', Rule::enum(ApplicationStatus::class)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application = $this->applicationService->updateStatus(
            applicationId: $applicationId,
            status       : $request->status,
            adminNotes   : $request->admin_notes,
        );

        return $this->success(
            new CareerApplicationResource($application),
            'Status lamaran berhasil diperbarui.'
        );
    }
}
