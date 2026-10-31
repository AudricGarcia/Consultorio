<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReminder extends Model
{
    protected $fillable = ['appointment_id', 'appointment_starts_at', 'reminder_offset_hours', 'scheduled_for', 'status', 'acknowledged_by', 'acknowledged_at', 'whatsapp_status', 'whatsapp_message_id', 'whatsapp_sent_at'];

    protected function casts(): array
    {
        return ['appointment_starts_at' => 'datetime', 'scheduled_for' => 'datetime', 'acknowledged_at' => 'datetime', 'whatsapp_sent_at' => 'datetime', 'reminder_offset_hours' => 'integer'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
