<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TimeEntry extends Model
{
    protected $fillable = [
        'work_date', 'module', 'source', 'task_type', 'task_key',
        'task_link', 'description', 'seconds', 'running_since', 'filled_form',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'running_since' => 'datetime',
            'filled_form' => 'boolean',
            'seconds' => 'integer',
        ];
    }

    public function isRunning(): bool
    {
        return $this->running_since !== null;
    }

    /** Accumulated seconds including the currently running segment. */
    public function totalSeconds(): int
    {
        $total = $this->seconds;

        if ($this->running_since) {
            $total += max(0, (int) $this->running_since->diffInSeconds(Carbon::now(), true));
        }

        return $total;
    }

    public function start(): void
    {
        static::query()->whereNotNull('running_since')
            ->where('id', '!=', $this->id ?? 0)
            ->get()
            ->each->pause();

        if (! $this->running_since) {
            $this->running_since = Carbon::now();
            $this->save();
        }
    }

    public function pause(): void
    {
        if ($this->running_since) {
            $this->seconds = $this->totalSeconds();
            $this->running_since = null;
            $this->save();
        }
    }

    /** 9000 => "2:30" (same style as the Google Sheet). */
    public static function formatSeconds(int $seconds): string
    {
        $minutes = (int) round($seconds / 60);

        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** "2:30" / "2.5" / "150m" => seconds. */
    public static function parseDuration(?string $value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }
        if (preg_match('/^(\d+):(\d{1,2})$/', $value, $m)) {
            return ((int) $m[1] * 60 + (int) $m[2]) * 60;
        }
        if (preg_match('/^(\d+)\s*m$/i', $value, $m)) {
            return (int) $m[1] * 60;
        }
        if (is_numeric($value)) {
            return (int) round((float) $value * 3600);
        }

        return 0;
    }

    /** Tab-separated row matching sheet columns B–H (Module … Filled form). */
    public function sheetRow(): string
    {
        $cells = [
            $this->module, $this->source, $this->task_type, $this->task_key,
            $this->duration, $this->description, $this->filled_form ? 'Done' : '',
        ];

        return implode("\t", array_map(fn ($v) => preg_replace('/[\t\r\n]+/', ' ', (string) $v), $cells));
    }

    public function getDurationAttribute(): string
    {
        return static::formatSeconds($this->totalSeconds());
    }
}
