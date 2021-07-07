<?php

namespace App\Wilayah;

use Illuminate\Database\Eloquent\Model;

class WilayahDetail extends Model
{
    /* Tukang Sayur Eloquent */

    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'wilayah_details';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeActive($query)
    {
        return $query
            ->where('status', 1)
            ->where('publish', 1);
    }
}
