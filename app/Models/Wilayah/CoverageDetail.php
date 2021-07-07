<?php

namespace App\Models\Wilayah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoverageDetail extends Model
{
    use SoftDeletes;
    
    protected $table = 'tm_coverage_detail';
    protected $guarded = [];
}
