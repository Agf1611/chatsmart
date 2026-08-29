<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MessageHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'message',
        'user_id',
        'message',
        'number',
        'send_by',
        'payload',
        'status',
        'whatsapp_message_id',
        'resolved_jid',
        'delivery_status',
        'sent_at',
        'delivered_at',
        'read_at',
        'type',
        'note',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public static function syncRecentDeliveryReceipts(): void
    {
        DB::statement(
            "UPDATE message_histories
             INNER JOIN whatsapp_outbound_messages
                ON whatsapp_outbound_messages.device_id = message_histories.device_id
               AND whatsapp_outbound_messages.whatsapp_message_id = message_histories.whatsapp_message_id
             SET message_histories.delivery_status = whatsapp_outbound_messages.delivery_status,
                 message_histories.delivered_at = whatsapp_outbound_messages.delivered_at,
                 message_histories.read_at = whatsapp_outbound_messages.read_at,
                 message_histories.updated_at = NOW()
             WHERE message_histories.whatsapp_message_id IS NOT NULL
               AND message_histories.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
    }

    public function device(){
        return $this->belongsTo(Device::class);
    }


}
