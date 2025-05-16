<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'wedding_hall_id',
        'user_id',
        'reservation_date',
        'number_of_guests',
        'customer_name',
        'customer_surname',
        'customer_phone',
        'total_price',
        'status',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'total_price' => 'decimal:2',
    ];

    public function weddingHall()
    {
        return $this->belongsTo(WeddingHall::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBooked($query)
    {
        return $query->where('status', 'booked');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
