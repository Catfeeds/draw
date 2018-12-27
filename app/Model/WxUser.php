<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxUser extends Model
{
    protected $table = 'wx_user';
    protected $dates = ['deleted_at'];
    protected $dateFormat = 'U';
    protected $primaryKey = 'wx_user_id';
}
