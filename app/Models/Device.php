<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'body',
        'webhook',
        'status',
        'message_sent',
        'webhook_read',
        'webhook_reject_call',
        'set_available',
        'webhook_typing',
    ];

    protected $casts = [
        'webhook_read' => 'boolean',
        'webhook_reject_call' => 'boolean',
        'set_available' => 'boolean',
        'webhook_typing' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function autoreplies()
    {
        return $this->hasMany(Autoreply::class, 'device_id');
    }

    public function aiBots()
    {
        return $this->hasMany(AiBot::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }


    
}
