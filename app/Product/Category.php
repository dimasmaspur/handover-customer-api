<?php

namespace App\Product;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'categories';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1)
            ->where('publish', 1)
            ->where('is_cust', 1);
    }

    public function image()
    {
        $string = trim(str_replace(' ', '-', $this->name));
        return 'cust-' . strtolower($string) . '.png';
    }
}
