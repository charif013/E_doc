<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Canceled = 'CANCELED';
    case Completed = 'COMPLETED';
}
