<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case InReview = 'IN_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Canceled = 'CANCELED';
    case Completed = 'COMPLETED';
}
