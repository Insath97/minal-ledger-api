<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FinanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_type',
        'reference_type',
        'reference_id',
        'amount',
        'description',
        'record_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'record_date' => 'date',
    ];

    /* Scopes */

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('record_type', 'income');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('record_type', 'expense');
    }

    public function scopeByDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if (!empty($from)) {
            $query->whereDate('record_date', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('record_date', '<=', $to);
        }
        return $query;
    }
}
