<?php

namespace App\Modules\Career\Services;

use App\Modules\Career\Enums\CareerStatus;
use App\Modules\Career\Models\Career;
use App\Modules\Career\Repositories\Contracts\CareerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CareerService
{
    public function __construct(
        private readonly CareerRepositoryInterface $careerRepository,
    ) {}

    // ── List & Find ───────────────────────────────────────────────────────────

    public function listPublished(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->careerRepository->listPublished($filters, $perPage);
    }

    public function listAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->careerRepository->listAdmin($filters, $perPage);
    }

    public function findBySlug(string $slug): Career
    {
        $career = $this->careerRepository->findBySlug($slug);

        if (! $career) {
            abort(404, 'Lowongan tidak ditemukan.');
        }

        return $career;
    }

    public function findById(string $id): Career
    {
        return $this->careerRepository->findOrFail($id);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data): Career
    {
        // Jika status langsung published, set published_at
        if (($data['status'] ?? null) === CareerStatus::Published->value) {
            $data['published_at'] = now();
        }

        return $this->careerRepository->create($data);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(string $id, array $data): Career
    {
        return DB::transaction(function () use ($id, $data) {
            $career = $this->careerRepository->findOrFail($id);

            // Track status transitions
            $newStatus = $data['status'] ?? null;
            if ($newStatus && $newStatus !== $career->status->value) {
                if ($newStatus === CareerStatus::Published->value && ! $career->published_at) {
                    $data['published_at'] = now();
                }
                if ($newStatus === CareerStatus::Closed->value) {
                    $data['closed_at'] = now();
                }
            }

            return $this->careerRepository->update($id, $data);
        });
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(string $id): void
    {
        $this->careerRepository->delete($id);
    }

    // ── Status ────────────────────────────────────────────────────────────────

    public function updateStatus(string $id, string $status): Career
    {
        $data = ['status' => $status];

        if ($status === CareerStatus::Published->value) {
            $career = $this->careerRepository->findOrFail($id);
            if (! $career->published_at) {
                $data['published_at'] = now();
            }
            $data['closed_at'] = null;
        }

        if ($status === CareerStatus::Closed->value) {
            $data['closed_at'] = now();
        }

        return $this->careerRepository->update($id, $data);
    }
}
