<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiBot extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function conversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function autoreplies()
    {
        return $this->hasMany(Autoreply::class);
    }

    public static function boot()
    {
        parent::boot();

        static::created(function () {
            clearCacheNode();
        });

        static::updated(function () {
            clearCacheNode();
        });

        static::deleted(function () {
            clearCacheNode();
        });
    }
}
