<?php

namespace App\Member;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table = 'tm_member_addresses';
    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->where('publish', 1)->where('status', 1);
    }

    public function scopePrimary($query)
    {
        return $query->active()->orderBy('is_primary', 'desc');
    }

    public function fullAddress()
    {
        $details = [];

        if ($this->address)
            $details[] = ucwords(strtolower($this->address));
        if ($this->adm_area_level_4)
            $details[] = $this->adm_area_level_4;
        if ($this->adm_area_level_3)
            $details[] = $this->adm_area_level_3;
        if ($this->adm_area_level_2)
            $details[] = $this->adm_area_level_2;
        if ($this->adm_area_level_1)
            $details[] = $this->adm_area_level_1;
        /* if ($this->country)
            $details[] = $this->country; */
        if ($this->postal_code)
            $details[] = $this->postal_code;

        return implode(', ', $details);
    }
}