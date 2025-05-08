<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeddingHallImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'wedding_hall_id',
        'image_path',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function weddingHall()
    {
        return $this->belongsTo(WeddingHall::class);
    }
}
