<?php

namespace App\Traits;

use App\Services\ActivityLogger;

/**
 * Loggable
 *
 * Attach this trait to any Eloquent model to automatically record
 * created, updated, and deleted events in the activity_logs table.
 *
 * Usage:
 *   use App\Traits\Loggable;
 *   class Vehicle extends Model {
 *       use Loggable;
 *   }
 *
 * To exclude sensitive or noisy fields from the diff log, override:
 *   protected array $loggableHidden = ['password', 'remember_token'];
 *
 * To disable logging on a model entirely per-request, call:
 *   $model->disableLogging()->update([...]);
 */
trait Loggable
{
    /**
     * Temporarily disable logging for this model instance.
     */
    public bool $loggingEnabled = true;

    public function disableLogging(): static
    {
        $this->loggingEnabled = false;
        return $this;
    }

    /**
     * Fields to redact from the activity-log diff (both old and new values).
     * Read by ActivityLogger::logModel() — a public accessor because the
     * $loggableHidden property is protected and the redaction happens in the
     * service, not on the model.
     *
     * @return array<int, string>
     */
    public function getLoggableHidden(): array
    {
        return $this->loggableHidden ?? [];
    }

    public static function bootLoggable(): void
    {
        static::created(function ($model) {
            if (! $model->loggingEnabled) return;
            ActivityLogger::logModel($model, 'created');
        });

        static::updated(function ($model) {
            if (! $model->loggingEnabled) return;
            // Redaction is centralised in ActivityLogger::logModel() so it
            // covers created/updated/deleted and both sides of the diff.
            ActivityLogger::logModel($model, 'updated');
        });

        static::deleted(function ($model) {
            if (! $model->loggingEnabled) return;
            ActivityLogger::logModel($model, 'deleted');
        });
    }
}
