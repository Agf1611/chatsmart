<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','tag_id','name','number'];

    protected static function booted()
    {
        static::saving(function ($contact) {
            $contact->number = normalizePhoneNumber($contact->number);
        });

        static::saved(function () {
            clearCacheNode();
        });

        static::deleted(function () {
            clearCacheNode();
        });
    }

    public function tag(){
        return $this->belongsTo(Tag::class);
    }
}
