<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Proposed = 'PROPOSED';
    case Pending = 'PENDING';
    case Acknowledged = 'ACKNOWLEDGED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Canceled = 'CANCELED';
}
