<?php

namespace App\Models\Widget;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Widget extends Model
{
    use SoftDeletes;

    protected $table = 'tm_widgets';
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

    public function type()
    {
        return $this->belongsTo(WidgetType::class, 'type_id');
    }

    public function details()
    {
        return $this->hasMany(WidgetDetail::class, 'widget_id');
    }
    
    public function images()
    {
        return $this->hasMany(WidgetImage::class, 'widget_id')
            ->orderBy('order');
    }

    public function scopeActive($query)
    {
        return $query->where('tm_widgets.status', 1)
            ->whereDate('tm_widgets.start_date', '<=', date('Y-m-d'))
            ->whereDate('tm_widgets.end_date', '>=', date('Y-m-d'));
    }
}
