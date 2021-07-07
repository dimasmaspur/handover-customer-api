<?php

namespace App\Models\Widget;

use Illuminate\Database\Eloquent\Model;

class WidgetPage extends Model
{
    protected $table = 'tm_widget_pages';
    protected $guarded = [];
    
    public function detail()
    {
        return $this->widgets();
    }
    
    public function widgets()
    {
        return $this->belongsToMany(
            Widget::class,
            'tr_widget_orders',
            'widget_page_id',
            'widget_id',
        )->withPivot('order');
    }
}
