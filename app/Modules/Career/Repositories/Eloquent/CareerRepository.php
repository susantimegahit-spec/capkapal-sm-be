<?php

namespace App\Modules\Career\Repositories\Eloquent;

use App\Modules\Career\Enums\CareerStatus;
use App\Modules\Career\Models\Career;
use App\Modules\Career\Repositories\Contracts\CareerRepositoryInterface;
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Pagination\LengthAwarePaginator;

class CareerRepository implements CareerRepositoryInterface
{
    public function __construct(protected Career $model) {}

    // ── Public listing (published only) ───────────────────────────────────────

    public function listPublished(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->withCount('applications')
            ->published()
            ->when($filters['employment_type'] ?? null,
                fn($q, $v) => $q->where('employment_type', $v))
            ->when($filters['location'] ?? null,
                fn($q, $v) => $q->where('location', 'ilike', "%{$v}%"))
            ->when($filters['search'] ?? null,
                fn($q, $v) => $q->where(fn($s) =>
                    $s->where('title', 'ilike', "%{$v}%")
                      ->orWhere('description', 'ilike', "%{$v}%")
                ))
            ->orderBy(...$this->resolveSort($filters['sort'] ?? 'latest'))
            ->paginate($perPage);
    }

    // ── Admin listing (all statuses) ──────────────────────────────────────────

    public function listAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->withCount('applications')
            ->withTrashed()
            ->when($filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->when($filters['employment_type'] ?? null,
                fn($q, $v) => $q->where('employment_type', $v))
            ->when($filters['search'] ?? null,
                fn($q, $v) => $q->where(fn($s) =>
                    $s->where('title', 'ilike', "%{$v}%")
                      ->orWhere('location', 'ilike', "%{$v}%")
                ))
            ->orderBy(...$this->resolveSort($filters['sort'] ?? 'latest'))
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?Career
    {
        return $this->model
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    // ── Base RepositoryInterface methods ──────────────────────────────────────

    public function all(array $filters = []): Collection
    {
        return $this->model->published()->get();
    }

    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->listPublished($filters, $perPage);
    }

    public function findById(string|int $id): ?Career
    {
        return $this->model->find($id);
    }

    public function findOrFail(string|int $id): Career
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Career
    {
        return $this->model->create($data);
    }

    public function update(string|int $id, array $data): Career
    {
        $career = $this->findOrFail($id);
        $career->update($data);

        return $career->fresh();
    }

    public function delete(string|int $id): bool
    {
        return $this->findOrFail($id)->delete();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function resolveSort(string $sort): array
    {
        return match ($sort) {
            'title_asc'  => ['title', 'asc'],
            'title_desc' => ['title', 'desc'],
            'oldest'     => ['created_at', 'asc'],
            default      => ['created_at', 'desc'],  // 'latest'
        };
    }
}
