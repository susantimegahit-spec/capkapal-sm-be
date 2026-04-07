<?php

namespace App\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface RepositoryInterface
{
  public function all(array $filters = []): Collection;
  public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator;
  public function findById(string|int $id): ?Model;
  public function findOrFail(string|int $id): Model;
  public function create(array $data): Model;
  public function update(string|int $id, array $data): Model;
  public function delete(string|int $id): bool;
}