<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OneSignalSetting extends Model
{
    protected $table = 'onesignal_settings';
    protected $fillable = ['app_id', 'api_key'];
}
