<?php

namespace App\Services\V2;

use LogicException;

class V2WriteGuard
{
    public function ensureEnabled(): void
    {
        if (! config('edoc.v2.enabled') || ! config('edoc.v2.write_enabled')) {
            throw new LogicException('V2 writes are disabled. Enable them only in an approved smoke-test or cutover window.');
        }
    }
}
