<?php

namespace App\Models\Wilayah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coverage extends Model
{
    use SoftDeletes;
    
    protected $table = 'tm_coverage';
    protected $guarded = [];
}
