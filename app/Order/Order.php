<?php

namespace App\Order;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $table = 'tr_orders';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function histories()
    {
        return $this->hasMany(OrderHistory::class, 'order_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'promo_code');
    }

    public function fullAddress()
    {
        $details = [];

        if ($this->cust_addr_detail)
            $details[] = ucwords(strtolower($this->cust_addr_detail));
        if ($this->cust_addr_adm_area_level_4)
            $details[] = $this->cust_addr_adm_area_level_4;
        if ($this->cust_addr_adm_area_level_3)
            $details[] = $this->cust_addr_adm_area_level_3;
        if ($this->cust_addr_adm_area_level_2)
            $details[] = $this->cust_addr_adm_area_level_2;
        if ($this->cust_addr_adm_area_level_1)
            $details[] = $this->cust_addr_adm_area_level_1;
        /* if ($this->cust_addr_country)
            $details[] = $this->cust_addr_country; */
        if ($this->cust_addr_postal_code)
            $details[] = $this->cust_addr_postal_code;

        return implode(', ', $details);
    }
}
