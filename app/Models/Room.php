<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';
    protected $primaryKey = 'room_id';
    public $timestamps = false;

    protected $fillable = [
        'room_number', 'room_type', 'price_per_night', 'branch', 'status',
    ];

    // One room has many reservations
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'room_id', 'room_id');
    }
}
