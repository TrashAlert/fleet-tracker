<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Log a model lifecycle event (created / updated / deleted).
     *
     * @param  Model        $model      The Eloquent model that changed
     * @param  string       $action     'created' | 'updated' | 'deleted'
     * @param  string|null  $description  Override the auto-generated description
     * @param  array        $context    Extra causer context (causer_type, causer_label)
     */
    public static function logModel(
        Model $model,
        string $action,
        ?string $description = null,
        array $context = []
    ): void {
        $subjectType  = class_basename($model);
        $subjectLabel = self::resolveLabel($model);

        $old = [];
        $new = [];

        if ($action === 'updated') {
            $old = $model->getOriginal();
            $new = $model->getDirty();

            // Remove noisy timestamp fields from diff
            foreach (['created_at', 'updated_at'] as $ts) {
                unset($old[$ts], $new[$ts]);
            }

            if (empty($new)) return; // nothing meaningful changed
        } elseif ($action === 'created') {
            $new = $model->getAttributes();
        } elseif ($action === 'deleted') {
            $old = $model->getAttributes();
        }

        $description ??= self::buildDescription($subjectType, $subjectLabel, $action, $old, $new);

        self::write([
            'subject_type'  => $subjectType,
            'subject_id'    => $model->getKey(),
            'subject_label' => $subjectLabel,
            'action'        => $action,
            'description'   => $description,
            'old_values'    => empty($old) ? null : $old,
            'new_values'    => empty($new) ? null : $new,
        ], $context);
    }

    /**
     * Log a custom system event (MQTT, alerts, delivery status changes, etc.)
     *
     * @param  string       $action       e.g. 'mqtt_received', 'overspeed_detected', 'delivered'
     * @param  string       $description  Human-readable message
     * @param  string|null  $subjectType  e.g. 'Vehicle', 'Shipment'
     * @param  int|null     $subjectId
     * @param  string|null  $subjectLabel
     * @param  array|null   $meta         Extra structured data to store in new_values
     * @param  array        $context      Causer override: ['causer_type' => ..., 'causer_label' => ...]
     */
    public static function logEvent(
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectLabel = null,
        ?array $meta = null,
        array $context = []
    ): void {
        self::write([
            'subject_type'  => $subjectType ?? 'System',
            'subject_id'    => $subjectId,
            'subject_label' => $subjectLabel,
            'action'        => $action,
            'description'   => $description,
            'old_values'    => null,
            'new_values'    => $meta,
        ], $context);
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private static function write(array $data, array $context = []): void
    {
        try {
            ActivityLog::create(array_merge([
                'causer_type'  => $context['causer_type']  ?? (app()->runningInConsole() ? 'system' : 'web'),
                'causer_label' => $context['causer_label'] ?? self::resolveCauserLabel(),
                'ip_address'   => app()->runningInConsole() ? null : Request::ip(),
                'user_agent'   => app()->runningInConsole() ? null : Request::userAgent(),
                'logged_at'    => now(),
            ], $data));
        } catch (\Throwable $e) {
            // Never let logging crash the application
            \Illuminate\Support\Facades\Log::error('ActivityLogger failed: ' . $e->getMessage());
        }
    }

    private static function resolveCauserLabel(): string
    {
        return Request::ip() ?? 'unknown';
    }

    private static function resolveLabel(Model $model): string
    {
        // Try common identifying fields in priority order
        foreach (['plate_number', 'tracking_code', 'name', 'title', 'email'] as $field) {
            if (!empty($model->{$field})) {
                return (string) $model->{$field};
            }
        }
        return class_basename($model) . '#' . $model->getKey();
    }

    private static function buildDescription(
        string $type,
        string $label,
        string $action,
        array $old,
        array $new
    ): string {
        $base = "{$type} [{$label}] was {$action}";

        if ($action === 'updated' && !empty($new)) {
            $fields = implode(', ', array_keys($new));
            $base  .= " — fields changed: {$fields}";
        }

        return $base;
    }
}
