<?php

namespace App\Member;

use Illuminate\Database\Eloquent\Model;

class PointHistory extends Model
{
    protected $table = 'tr_points_histories';
    protected $guarded = [];
    public $timestamps = false;
}