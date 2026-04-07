<?php

namespace App\Modules\Auth\Enums;

enum UserStatus: string
{
  case Active    = 'active';
  case Inactive  = 'inactive';
  case Suspended = 'suspended';
}