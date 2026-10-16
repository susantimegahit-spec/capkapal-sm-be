<?php

namespace App\Modules\Career\Enums;

enum CareerStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Closed    = 'closed';
}
