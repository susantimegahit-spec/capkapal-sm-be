<?php

namespace App\Modules\Admin\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use Illuminate\Pagination\LengthAwarePaginator;

class UserManagementService
{
    public function list(array $filters): LengthAwarePaginator
    {
        return User::with(['profile', 'roles'])
            ->withTrashed()
            ->when($filters['user_type'] ?? null,
                fn($q, $v) => $q->where('user_type', $v))
            ->when($filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->when($filters['role'] ?? null,
                fn($q, $v) => $q->role($v))
            ->when($filters['search'] ?? null,
                fn($q, $v) => $q->where(fn($s) =>
                $s->where('name', 'ilike', "%{$v}%")
                    ->orWhere('email', 'ilike', "%{$v}%")
                    ->orWhere('phone', 'ilike', "%{$v}%")
                ))
            ->when($filters['date_from'] ?? null,
                fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function suspend(string $userId, string $reason, string $actorId): User
    {
        if ($userId === $actorId) {
            throw new \DomainException('Anda tidak dapat men-suspend akun Anda sendiri.');
        }

        $user = User::findOrFail($userId);

        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            throw new \DomainException('Admin tidak bisa di-suspend.');
        }

        $user->update(['status' => 'suspended']);

        // Revoke all tokens
        $user->tokens()->delete();

        activity('admin')
            ->performedOn($user)
            ->causedBy(User::find($actorId))
            ->withProperties(['reason' => $reason])
            ->log('user_suspended');

        return $user;
    }

    public function activate(string $userId, string $actorId): User
    {
        $user = User::findOrFail($userId);
        $user->update(['status' => 'active']);

        activity('admin')
            ->performedOn($user)
            ->causedBy(User::find($actorId))
            ->log('user_activated');

        return $user;
    }

    public function updateRole(string $userId, string $role, string $actorId): User
    {
        if ($userId === $actorId) {
            throw new \DomainException('Anda tidak dapat mengubah role Anda sendiri.');
        }

        $actor = User::findOrFail($actorId);
        if (! $actor->hasAnyRole(['admin', 'superadmin'])) {
            throw new \DomainException('Anda tidak memiliki izin untuk mengubah role user.');
        }

        $user = User::findOrFail($userId);

        // Jika target adalah admin/superadmin, hanya superadmin yang bisa mengubah role-nya (opsional, tapi disarankan)
        if ($user->hasAnyRole(['admin', 'superadmin']) && ! $actor->hasRole('superadmin')) {
            throw new \DomainException('Hanya superadmin yang dapat mengubah role administrator lain.');
        }

        $allowedRoles = ['admin', 'editor', 'consumer', 'umkm', 'distributor', 'superadmin'];

        if (! in_array($role, $allowedRoles)) {
            throw new \InvalidArgumentException("Role tidak valid: {$role}");
        }

        $user->syncRoles([$role]);

        activity('admin')
            ->performedOn($user)
            ->causedBy(User::find($actorId))
            ->withProperties(['new_role' => $role])
            ->log('role_changed');

        return $user->fresh('roles');
    }

    public function getDetail(string $userId): User
    {
        $validStatuses = [
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Completed,
        ];

        return User::with([
            'profile',
            'roles',
            'orders' => fn($q) => $q->latest()->limit(10),
        ])
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->withCount(['orders as successful_orders_count' => function ($query) use ($validStatuses) {
                $query->whereIn('status', $validStatuses);
            }])
            ->withSum(['orders as successful_orders_total' => function ($query) use ($validStatuses) {
                $query->whereIn('status', $validStatuses);
            }], 'total')
            ->findOrFail($userId);
    }
}
