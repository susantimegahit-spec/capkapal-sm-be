<?php

namespace App\Modules\Career\Enums;

enum ApplicationStatus: string
{
    case Pending  = 'pending';
    case Reviewed = 'reviewed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
