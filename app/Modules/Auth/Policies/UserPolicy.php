<?php

namespace App\Modules\Auth\Policies;

use App\Modules\Auth\Models\User;

class UserPolicy
{
  public function viewAny(User $auth): bool
  {
    return $auth->hasRole(['admin', 'editor']);
  }

  public function view(User $auth, User $user): bool
  {
    return $auth->id === $user->id || $auth->hasRole('admin');
  }

  public function update(User $auth, User $user): bool
  {
    return $auth->id === $user->id || $auth->hasRole('admin');
  }

  public function delete(User $auth, User $user): bool
  {
    return $auth->hasRole('admin') && $auth->id !== $user->id;
  }
}