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
        'subject',
        'message',
        'source',
        'status',
    ];
}
