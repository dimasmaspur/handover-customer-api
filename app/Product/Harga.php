<?php

namespace App\Product;

use App\Wilayah\Wilayah;
use Illuminate\Database\Eloquent\Model;

class Harga extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'hargas';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1)->where('isdefault', 1);
    }

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'wilayah_id');
    }
}
