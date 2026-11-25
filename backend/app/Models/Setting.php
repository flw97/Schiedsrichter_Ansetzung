<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key): ?string
    {
        return self::find($key)?->value;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
