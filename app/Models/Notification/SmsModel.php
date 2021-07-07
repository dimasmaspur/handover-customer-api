<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Model;

class SmsModel extends model {

    protected $connection = "mysql";

    protected $table = 'sms_queues';

    public $timestamps = false;

}