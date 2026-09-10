<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Revoked = 'REVOKED';
}
