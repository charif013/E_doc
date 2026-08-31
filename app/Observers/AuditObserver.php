<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        app(AuditLogger::class)->log('created', $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        app(AuditLogger::class)->modelChanged('updated', $model);
    }

    public function deleted(Model $model): void
    {
        app(AuditLogger::class)->log('deleted', $model, $model->getOriginal(), []);
    }

    public function restored(Model $model): void
    {
        app(AuditLogger::class)->log('restored', $model, [], $model->getAttributes());
    }
}
