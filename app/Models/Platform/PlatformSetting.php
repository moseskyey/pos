<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $connection = 'central';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];
}
