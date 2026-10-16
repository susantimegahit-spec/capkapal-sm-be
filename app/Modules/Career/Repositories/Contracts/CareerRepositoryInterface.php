<?php

namespace App\Modules\Career\Repositories\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Modules\Career\Models\Career;
use Illuminate\Pagination\LengthAwarePaginator;

interface CareerRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?Career;

    public function listPublished(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function listAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator;
}
