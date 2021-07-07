<?php

namespace App\Order;

use App\Member\Address;
use App\User;
use Illuminate\Database\Eloquent\Model;

class OrderMapping extends Model
{
    protected $table = 'tr_order_mapping';

    protected $guarded = [];

    public $timestamps = false;

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
    
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'member_id');
    }
    
    public function member()
    {
        return $this->user();
    }
}
