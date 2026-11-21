<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $table = 'guests';
    protected $primaryKey = 'guest_id';
    public $timestamps = false;

    protected $fillable = ['full_name', 'email', 'phone'];

    // One guest has many reservations
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'guest_id', 'guest_id');
    }
}
