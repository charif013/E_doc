<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'DRAFT';
    case Registered = 'REGISTERED';
    case InReview = 'IN_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Completed = 'COMPLETED';
    case Canceled = 'CANCELED';
    case Archived = 'ARCHIVED';
}
