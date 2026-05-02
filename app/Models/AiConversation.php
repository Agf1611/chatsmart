<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'paused_until' => 'datetime',
        'last_message_at' => 'datetime',
        'last_bot_reply_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bot()
    {
        return $this->belongsTo(AiBot::class, 'ai_bot_id');
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function messages()
    {
        return $this->hasMany(AiConversationMessage::class);
    }
}
