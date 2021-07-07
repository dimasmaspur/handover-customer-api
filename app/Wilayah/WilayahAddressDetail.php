<?php

namespace App\Wilayah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WilayahAddressDetail extends Model
{
    /* Lapak Tukang Sayur Eloquent */

    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'wilayah_address_details';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function detail()
    {
        return $this->belongsTo(WilayahDetail::class, 'wilayah_detail_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->where('publish', 1);
    }

    public function scopeWhereDistance($query, $lat, $lng, $order = 'asc')
    {
        return $query->addSelect(DB::raw("(ABS({$lat} - lat) + ABS({$lng} - lng) ) AS distance"))
            ->orderBy('distance', $order);
    }
}
