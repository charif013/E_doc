<?php

namespace App\Policies;

use App\Models\DocumentAccessRequest;
use App\Models\User;

class DocumentAccessRequestPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function update(User $user, DocumentAccessRequest $request): bool
    {
        return $request->document?->created_by === $user->id || $user->hasRole('saraban');
    }
}
