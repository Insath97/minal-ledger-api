<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'payload',
        'level',
        'ip_address',
        'user_agent',
        'url',
        'method',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * Get the user who performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* Scopes */

    /**
     * Scope a query to search activity logs by action, module, description, ip_address, url, or user details.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('action', 'like', "%{$search}%")
              ->orWhere('module', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('ip_address', 'like', "%{$search}%")
              ->orWhere('url', 'like', "%{$search}%")
              ->orWhereHas('user', function (Builder $uq) use ($search) {
                  $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
              });
        });
    }

    public function scopeByModule(Builder $query, ?string $module): Builder
    {
        if (empty($module)) {
            return $query;
        }

        return $query->where('module', $module);
    }

    public function scopeByAction(Builder $query, ?string $action): Builder
    {
        if (empty($action)) {
            return $query;
        }

        return $query->where('action', $action);
    }

    public function scopeByLevel(Builder $query, ?string $level): Builder
    {
        if (empty($level)) {
            return $query;
        }

        return $query->where('level', $level);
    }

    public function scopeByUser(Builder $query, ?int $userId): Builder
    {
        if (empty($userId)) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }
}
