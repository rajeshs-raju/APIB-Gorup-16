<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'delivery_personnel_id', 'status'
    ];

    public function order() {
        return $this->belongsTo(Order::class);
    }

    public function deliveryPersonnel() {
        return $this->belongsTo(User::class, 'delivery_personnel_id');
    }
}
