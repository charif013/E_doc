<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLogger
{
    private const REDACTED_FIELDS = [
        'password', 'pin', 'remember_token', 'content',
        'creator_signature', 'supervisor_signature', 'inspector_signature',
        'head_signature', 'palad_signature', 'nayok_signature', 'signature',
    ];

    public function log(string $event, ?Model $subject = null, array $old = [], array $new = []): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->sanitize($old),
            'new_values' => $this->sanitize($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1000, '') : null,
            'request_id' => $request?->headers->get('X-Request-ID'),
        ]);
    }

    public function modelChanged(string $event, Model $model): AuditLog
    {
        $changes = $model->getChanges();
        $keys = array_keys($changes);

        return $this->log($event, $model, Arr::only($model->getOriginal(), $keys), $changes);
    }

    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::REDACTED_FIELDS, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_string($value) && mb_strlen($value) > 2000) {
                $values[$key] = mb_substr($value, 0, 2000).'…';
            }
        }

        return $values;
    }
}
