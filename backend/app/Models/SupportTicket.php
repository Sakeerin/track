<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'ticket_number',
        'tracking_number',
        'name',
        'email',
        'email_hash',
        'subject',
        'message',
        'source',
        'status',
    ];

    protected $casts = [
        'email' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (SupportTicket $ticket): void {
            if (!$ticket->isDirty('email')) {
                return;
            }

            $value = trim((string) $ticket->email);
            $ticket->email_hash = $value === ''
                ? null
                : hash('sha256', strtolower($value));
        });
    }
}
