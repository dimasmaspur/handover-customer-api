<?php

namespace App\Product;

use Illuminate\Database\Eloquent\Model;

class CategoryMapping extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'categories_mapping';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeActive($query)
    {
        return $query->where('categories_mapping.status', 1)
            ->where('categories_mapping.publish', 1)
            ->where('categories_mapping.app_code', 1);
    }
}
