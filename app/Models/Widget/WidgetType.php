<?php

namespace App\Models\Widget;

use Illuminate\Database\Eloquent\Model;

class WidgetType extends Model
{
    protected $table = 'tm_widget_types';
    protected $guarded = [];

    public function widgets()
    {
        return $this->hasMany(Widget::class, 'type_id');
    }
}
