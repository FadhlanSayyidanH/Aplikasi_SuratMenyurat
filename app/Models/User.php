<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable([
    'username', 'password', 'nama', 'role',
    'token', 'token_expires_at', 'file_token', 'file_token_expires_at',
    'failed_login_attempts', 'locked_until',
    'boleh_input_masuk', 'boleh_input_keluar',
])]
#[Hidden(['password', 'token', 'file_token', 'web_session_id'])]
class User extends Authenticatable
{
    use Notifiable, HasPushSubscriptions;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'file_token_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'boleh_input_masuk' => 'boolean',
            'boleh_input_keluar' => 'boolean',
        ];
    }

    public function bagMemberships()
    {
        return $this->hasMany(BagMember::class, 'user_id');
    }

    public function bagDisposisiMemberships()
    {
        return $this->hasMany(BagDisposisiAnggota::class, 'user_id');
    }
}
