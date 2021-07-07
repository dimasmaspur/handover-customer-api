<?php

namespace App\Product;

use Illuminate\Database\Eloquent\Model;

class IndexHarga extends Model
{
    protected $connection = 'mysql_cdb';
    protected $table = 'indeks_hargas';
    public $timestamps = false;
}
