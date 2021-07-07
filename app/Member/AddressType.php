<?php

namespace App\Member;

use Illuminate\Database\Eloquent\Model;

class AddressType extends Model
{
    protected $table = 'tm_tipe_alamat';
    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];
}