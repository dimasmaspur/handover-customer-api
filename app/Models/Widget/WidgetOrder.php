<?php

namespace App\Models\Widget;

use Illuminate\Database\Eloquent\Model;

class WidgetOrder extends Model
{
    protected $table = 'tr_widget_orders';
    protected $guarded = [];

    public function widget()
    {
        return $this->belongsTo(Widget::class, 'widget_id');
    }
}
