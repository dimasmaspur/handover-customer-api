<?php

namespace App\Models\Recomend;

use Illuminate\Database\Eloquent\Model;

class RecomendDetail extends Model
{
    protected $table = 'tm_recomend_detail';
    protected $guarded = [];
    public $timestamps = false;

    public function widget()
    {
        return $this->belongsTo(Recomend::class, 'recomend_id');
    }
}
