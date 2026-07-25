<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'email',
        'id_type',
        'id_number',
        'phone',
        'phone_secondary',
        'address_line1',
        'address_line2',
        'city',
        'profile_image',
        'nic_image',
        'outstanding_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'outstanding_balance' => 'decimal:2',
    ];

    /**
     * Boot model events to auto-generate customer code if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function ($customer) {
            if (empty($customer->code)) {
                $customer->code = static::generateNextCode();
            }
        });
    }

    /**
     * Generate next sequential customer code (e.g. CUST-00001).
     */
    public static function generateNextCode(): string
    {
        $maxId = static::max('id') ?? 0;
        $nextNum = $maxId + 1;

        do {
            $code = 'CUST-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
            $exists = static::where('code', $code)->exists();
            if ($exists) {
                $nextNum++;
            }
        } while ($exists);

        return $code;
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search customers by name, code, phone, email, id_number, or city.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('phone_secondary', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('id_number', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%");
        });
    }
}
