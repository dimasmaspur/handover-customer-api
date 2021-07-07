<?php

namespace App\Models\Authentication;

use Illuminate\Database\Eloquent\Model;

class AuthMemberModel extends model {

    protected $connection = "mysql";

    protected $table = 'auth_members';

    public $timestamps = false;

}