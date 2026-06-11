<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

enum AvailabilityVerdict: string
{
    case Available = 'available';

    case Unavailable = 'unavailable';

    case Missing = 'missing';
}
