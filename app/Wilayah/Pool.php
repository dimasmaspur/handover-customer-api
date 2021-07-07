<?php

namespace App\Wilayah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pool extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'wilayah_pools';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'wilayah_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->where('publish', 1);
    }

    public function scopeWhereDistance($query, $lat, $lng, $order = 'asc')
    {
        $query->addSelect(DB::raw("(ABS({$lat} - lat) + ABS({$lng} - lng) ) AS distance"))
            ->orderBy('distance', $order);
    }
}
