<?php

namespace App\Product;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Pricing extends Model
{
    protected $connection = 'mysql_ec';
    protected $table = 'tr_pricing';
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

    public function calculate($unitprice = 0)
    {
        $markup = $this->amount + $unitprice;

        if ($this->tipe == 1 && $unitprice > 0) {
        
            $markup = 100 * $unitprice / (100 - $this->amount);
            $markup = $markup / 100;
            $markup = round($markup);
            $markup = $markup * 100;
        }

        return $markup;
    }
}
