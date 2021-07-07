<?php

namespace App\Models\Authentication;

use Illuminate\Database\Eloquent\Model;

class AuthenticationModel extends model {

    protected $connection = "mysql";

    protected $table = 'auth_users';

    public $timestamps = false;

}