<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AuditableModelObserver
{
    public function created(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        AuditLog::log(
            AuditLog::ACTION_CREATE,
            auth()->user(),
            get_class($model),
            (string) $model->getKey(),
            null,
            $model->getAttributes(),
            $this->metadata()
        );
    }

    public function updated(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $original = [];
        foreach (array_keys($changes) as $key) {
            if ($key === 'updated_at') {
                continue;
            }

            $original[$key] = $model->getOriginal($key);
        }

        AuditLog::log(
            AuditLog::ACTION_UPDATE,
            auth()->user(),
            get_class($model),
            (string) $model->getKey(),
            $original,
            $changes,
            $this->metadata()
        );
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        AuditLog::log(
            AuditLog::ACTION_DELETE,
            auth()->user(),
            get_class($model),
            (string) $model->getKey(),
            $model->getAttributes(),
            null,
            $this->metadata()
        );
    }

    private function shouldSkip(Model $model): bool
    {
        return $model instanceof AuditLog || !Schema::hasTable('audit_logs');
    }

    private function metadata(): array
    {
        return [
            'source' => app()->runningInConsole() ? 'console' : 'http',
        ];
    }
}
