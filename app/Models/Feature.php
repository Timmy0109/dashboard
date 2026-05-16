<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = ['key', 'name', 'description', 'category', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function defaults(): array
    {
        return self::where('is_default', true)->pluck('key')->toArray();
    }
}
