<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TelegramUser extends Model
{
    protected $table = 'telegram_users';

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'language_code',
        'last_seen_at',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function session(): HasOne
    {
        return $this->hasOne(UserSession::class, 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class, 'user_id');
    }
}
