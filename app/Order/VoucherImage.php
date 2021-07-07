<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class VoucherImage extends Model
{
    protected $table = 'tm_voucher_image';
    protected $guarded = [];
    public $timestamps = false;

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
