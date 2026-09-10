<?php

namespace App\Enums;

enum WorkflowStepStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Skipped = 'SKIPPED';
    case Canceled = 'CANCELED';
}
