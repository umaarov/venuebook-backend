<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeddingHall extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'district_id',
        'address',
        'capacity',
        'price_per_seat',
        'phone',
        'owner_id',
        'status',
    ];

    protected $casts = [
        'price_per_seat' => 'decimal:2',
    ];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function images()
    {
        return $this->hasMany(WeddingHallImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(WeddingHallImage::class)->where('is_primary', true);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
