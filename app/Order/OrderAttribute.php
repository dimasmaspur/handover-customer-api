<?php

namespace App\Order;

use Illuminate\Database\Eloquent\Model;

class OrderAttribute extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $table = 'tr_order_attributes';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];
}
