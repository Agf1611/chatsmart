<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Autoreply extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'reply' => 'array',
        'reply_config' => 'array',
        'schedule_days' => 'array',
        'is_quoted' => 'boolean',
    ];

    public const TRANSPORT_POLICIES = [
        'interactive_preferred' => 'Interactive preferred',
        'text_fallback' => 'Text fallback',
    ];


    public function user(){
        return $this->belongsTo(User::class);
    }

    public function device (){
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function aiBot()
    {
        return $this->belongsTo(AiBot::class, 'ai_bot_id');
    }

    public function phonebook()
    {
        return $this->belongsTo(Tag::class, 'contact_tag_id');
    }

    public static function boot(){
        parent::boot();
        
        static::updated(function($autoreply){
            clearCacheNode();
        });

        static::created(function($autoreply){
            clearCacheNode();
        });

        static::deleted(function ($autoreply) {
            clearCacheNode();
        });
    }
  
}
