<?php

namespace App\Order;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'payment_types';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function steps()
    {
        return $this->hasMany(PaymentTypeStep::class, 'payment_type_id')
            ->whereNull('parent_id')
            ->orderBy('level');
    }
}
