<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    protected $table = 'tr_log_otp_request';
    protected $guarded = [];
    public $timestamps = false;
}
