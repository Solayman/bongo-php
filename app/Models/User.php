<?php

namespace App\Models;

use System\Database\HasMany;
use System\Database\Model;
use System\Mail\Mail;

class User extends Model
{
    protected static $table = 'users';
    protected array $fillable = ['name', 'email', 'password', 'role', 'phone'];
    protected array $hidden = ['password', 'remember_token'];
    protected array $casts = ['id' => 'int'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasVerifiedEmail(): bool
    {
        return !empty($this->email_verified_at);
    }

    public function markEmailAsVerified(): void
    {
        $this->email_verified_at = date('Y-m-d H:i:s');   // not fillable: a form can never set it
        $this->save();
    }

    /** Email a link that verifies the address (valid 60 minutes; it stops working if the email changes) */
    public function sendEmailVerification(): void
    {
        $link = signed_route('shop.verification.verify', ['id' => $this->id, 'hash' => sha1((string) $this->email)], 60);
        Mail::to($this->email, $this->name)
            ->subject(__('Verify your email address'))
            ->view('emails.verify-email', ['user' => $this, 'link' => $link])
            ->send();
    }
}
