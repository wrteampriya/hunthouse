<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chats extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at'];

    protected $table = 'chats';

    protected $fillable = ['sender_id', 'receiver_id', 'property_id', 'message', 'is_read', 'file', 'audio', 'sender_role_context', 'receiver_role_context', 'created_at', 'updated_at'];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(static function ($chat) {
            if (collect($chat)->isNotEmpty()) {
                // before delete() method call this

                // Delete File
                if ($chat->getRawOriginal('file') != '') {
                    $file = $chat->getRawOriginal('file');
                    if (file_exists(public_path('images').config('global.CHAT_FILE').$file)) {
                        unlink(public_path('images').config('global.CHAT_FILE').$file);
                    }
                }

                // Delete Audio
                if ($chat->getRawOriginal('audio') != '') {
                    $audio = $chat->getRawOriginal('audio');
                    if (file_exists(public_path('images').config('global.CHAT_AUDIO').$audio)) {
                        unlink(public_path('images').config('global.CHAT_AUDIO').$audio);
                    }
                }
            }
        });
    }

    public function sender()
    {
        return $this->belongsTo(Customer::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Customer::class, 'receiver_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function getFileAttribute($file)
    {
        return ! empty($file) ? FileService::getFileUrl(config('global.CHAT_FILE').$file) : null;
    }

    public function getAudioAttribute($value)
    {
        return ! empty($value) ? FileService::getFileUrl(config('global.CHAT_AUDIO').$value) : null;
    }
}
