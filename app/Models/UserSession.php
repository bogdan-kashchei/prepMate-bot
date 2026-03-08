<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id',
        'category',
        'level',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }
}
