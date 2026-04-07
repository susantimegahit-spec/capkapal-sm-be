<?php

namespace App\Modules\Auth\Enums;

enum UserType: string
{
  case User     = 'user';
  case Agen     = 'agen';
  case Admin    = 'admin';

  public function label(): string
  {
    return match ($this) {
      self::User        => 'User',
      self::Agen        => 'Agen',
      self::Admin       => 'Admin',
    };
  }

  public function defaultRole(): string
  {
    return match ($this) {
      self::User    => 'user',
      self::Agen        => 'agen',
      self::Admin => 'admin',
    };
  }
}
