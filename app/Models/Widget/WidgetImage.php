<?php

namespace App\Models\Widget;

use Illuminate\Database\Eloquent\Model;

class WidgetImage extends Model
{
    protected $table = 'tm_widget_images';
    protected $guarded = [];
    public $timestamps = false;

    public function widget()
    {
        return $this->belongsTo(Widget::class, 'widget_id');
    }
}
