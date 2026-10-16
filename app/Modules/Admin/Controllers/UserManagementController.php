<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Admin\Services\UserManagementService;
use App\Modules\Auth\Resources\UserResource;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class UserManagementController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly UserManagementService $userService) {}

    // GET /api/v1/admin/users
    public function index(Request $request): JsonResponse
    {
        // Simpan hasil validasi langsung ke variabel $validated
        $validated = $request->validate([
            'user_type' => ['nullable', 'in:admin,user,agen'],
            'status'    => ['nullable', 'in:active,inactive,suspended'],
            'role'      => ['nullable', 'string'],
            'search'    => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'per_page'  => ['nullable', 'integer', 'max:100'],
        ]);

        // Passing array $validated ke service
        $users = $this->userService->list($validated);

        return $this->success(
            UserResource::collection($users)->response()->getData(true)
        );
    }

    // GET /api/v1/admin/users/{userId}
    public function show(string $userId): JsonResponse
    {
        return $this->success(
            new UserResource($this->userService->getDetail($userId))
        );
    }

    // POST /api/v1/admin/users/{userId}/suspend
    public function suspend(Request $request, string $userId): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $user = $this->userService->suspend($userId, $request->reason, $request->user()->id);

        return $this->success(new UserResource($user), 'User berhasil di-suspend.');
    }

    // POST /api/v1/admin/users/{userId}/activate
    public function activate(Request $request, string $userId): JsonResponse
    {
        $user = $this->userService->activate($userId, $request->user()->id);

        return $this->success(new UserResource($user), 'User berhasil diaktifkan.');
    }

    // PATCH /api/v1/admin/users/{userId}/role
    public function updateRole(Request $request, string $userId): JsonResponse
    {
        $request->validate(['role' => ['required', 'string', 'exists:roles,name']]);

        $user = $this->userService->updateRole($userId, $request->role, $request->user()->id);

        return $this->success(new UserResource($user), 'Role berhasil diperbarui.');
    }
}
