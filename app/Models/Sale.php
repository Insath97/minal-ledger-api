<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'business_type',
        'reference_number',
        'invoice_number',
        'bill_image',
        'total_amount',
        'paid_amount',
        'due_amount',
        'payment_status',
        'sale_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'sale_date' => 'date',
    ];

    /**
     * Boot model events to auto-generate reference number if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function ($sale) {
            if (empty($sale->reference_number)) {
                $sale->reference_number = static::generateNextReferenceNumber();
            }
        });
    }

    /**
     * Generate next sequential sale reference number (e.g. INV-YYYYMMDD-00001).
     */
    public static function generateNextReferenceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd') . '-';
        $latest = static::where('reference_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $number = intval(substr($latest->reference_number, -5)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    /* Relationships */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    /* Scopes */

    public function scopeByBusinessType(Builder $query, ?string $type): Builder
    {
        if (empty($type)) {
            return $query;
        }
        return $query->where('business_type', $type);
    }

    public function scopeByPaymentStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }
        return $query->where('payment_status', $status);
    }

    public function scopeByCustomer(Builder $query, ?int $customerId): Builder
    {
        if (empty($customerId)) {
            return $query;
        }
        return $query->where('customer_id', $customerId);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('reference_number', 'like', "%{$search}%")
              ->orWhere('invoice_number', 'like', "%{$search}%")
              ->orWhereHas('customer', function (Builder $cq) use ($search) {
                  $cq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
              });
        });
    }
}
