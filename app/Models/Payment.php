<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'cheque_id',
        'total_amount',
        'payment_method',
        'payment_date',
        'proof_image_path',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /* Relationships */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function paymentSales(): HasMany
    {
        return $this->hasMany(PaymentSale::class);
    }

    /* Scopes */

    public function scopeByCustomer(Builder $query, ?int $customerId): Builder
    {
        if (empty($customerId)) {
            return $query;
        }
        return $query->where('customer_id', $customerId);
    }

    public function scopeByPaymentMethod(Builder $query, ?string $method): Builder
    {
        if (empty($method)) {
            return $query;
        }
        return $query->where('payment_method', $method);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('notes', 'like', "%{$search}%")
              ->orWhereHas('customer', function (Builder $cq) use ($search) {
                  $cq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
              });
        });
    }
}
