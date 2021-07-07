<?php

namespace App\Product;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MaxQty extends Model
{
    protected $connection = 'mysql_ec';
    protected $table = 'tr_max_qty';
    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeActive($query)
    {
        return $query/* ->where('status', 1) */
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now());
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
