<?php

namespace App\Order;

use Illuminate\Database\Eloquent\Model;

class PaymentTypeStep extends Model
{
    protected $connection = 'mysql_cdb';
    protected $table = 'payment_types_step';

    public function details()
    {
        return $this->hasMany(PaymentTypeStep::class, 'parent_id')->orderBy('level');
    }
}
