<?php

namespace App\Product;

use Illuminate\Database\Eloquent\Model;

class Picture extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'productpictures';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];
}
