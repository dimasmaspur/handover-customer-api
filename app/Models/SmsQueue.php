<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsQueue extends Model
{
    protected $connection = 'mysql_cdb';
    protected $table = 'sms_queues';
    // protected $table = 'tm_sms_queues';
    protected $guarded = [];
    public $timestamps = false;
}
