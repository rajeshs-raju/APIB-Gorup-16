<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id', 'name', 'address', 'hours_of_operation'
    ];

    public function menus() {
        return $this->hasMany(Menu::class);
    }

    public function orders() {
        return $this->hasMany(Order::class);
    }

    public function owner() {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
