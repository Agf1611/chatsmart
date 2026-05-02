<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomingMessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'chat_jid',
        'message_count',
        'first_received_at',
        'last_received_at',
    ];

    protected $casts = [
        'first_received_at' => 'datetime',
        'last_received_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
