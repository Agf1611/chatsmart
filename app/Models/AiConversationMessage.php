<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConversationMessage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
