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

    public static function bootLoggable(): void
    {
        static::created(function ($model) {
            if (! $model->loggingEnabled) return;
            ActivityLogger::logModel($model, 'created');
        });

        static::updated(function ($model) {
            if (! $model->loggingEnabled) return;

            // Strip fields the model wants hidden from logs
            $hidden = $model->loggableHidden ?? [];
            foreach ($hidden as $field) {
                $model->original[$field] = '***';
                if (isset($model->changes[$field])) {
                    $model->changes[$field] = '***';
                }
            }

            ActivityLogger::logModel($model, 'updated');
        });

        static::deleted(function ($model) {
            if (! $model->loggingEnabled) return;
            ActivityLogger::logModel($model, 'deleted');
        });
    }
}
