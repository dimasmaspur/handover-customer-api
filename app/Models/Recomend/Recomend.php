<?php

namespace App\Models\Recomend;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recomend extends Model
{
    use SoftDeletes;

    protected $table = 'tm_recomend';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'detail_type' => 'integer',
        'show_all_button' => 'integer',
        'is_clickable' => 'integer',
        'status' => 'integer',
        'source' => 'integer',
        'external_source_id' => 'integer',
    ];

    public function details()
    {
        return $this->hasMany(RecomendDetail::class, 'recomend_id');
    }
    
 

    public function scopeActive($query)
    {
        return $query->where('tm_recomend.status', 1)
            ->whereDate('tm_recomend.start_date', '<=', date('Y-m-d'))
            ->whereDate('tm_recomend.end_date', '>=', date('Y-m-d'));
    }
}
