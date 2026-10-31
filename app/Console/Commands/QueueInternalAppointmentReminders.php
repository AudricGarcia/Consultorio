<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:queue-internal-reminders')]
#[Description('Genera recordatorios internos del equipo sin contactar pacientes')]
class QueueInternalAppointmentReminders extends Command
{
    public function handle(): int
    {
        $now = now();
        $created = 0;
        foreach ([48, 24, 2] as $offsetHours) {
            $windowEnd = $now->copy()->addHours($offsetHours);
            $windowStart = $now->copy()->addHours($offsetHours - 1);
            Appointment::query()->whereIn('status', ['scheduled', 'confirmed'])
                ->where('starts_at', '>', $now)->whereBetween('starts_at', [$windowStart, $windowEnd])
                ->orderBy('starts_at')->chunkById(100, function ($appointments) use ($offsetHours, $now, &$created): void {
                    foreach ($appointments as $appointment) {
                        $reminder = AppointmentReminder::firstOrNew(['appointment_id' => $appointment->id, 'reminder_offset_hours' => $offsetHours]);
                        if (! $reminder->exists) {
                            $reminder->fill(['appointment_starts_at' => $appointment->starts_at, 'scheduled_for' => $now, 'status' => 'pending'])->save();
                            $created++;
                        } elseif ($reminder->appointment_starts_at?->ne($appointment->starts_at) || $reminder->status === 'cancelled') {
                            $reminder->update(['appointment_starts_at' => $appointment->starts_at, 'scheduled_for' => $now, 'status' => 'pending', 'acknowledged_by' => null, 'acknowledged_at' => null]);
                            $created++;
                        }
                    }
                });
        }

        AppointmentReminder::query()->where('status', 'pending')->whereHas('appointment', fn ($query) => $query->whereIn('status', ['cancelled', 'no_show']))
            ->update(['status' => 'cancelled', 'updated_at' => $now]);

        $this->components->info("Recordatorios internos nuevos: {$created}. No se enviaron mensajes externos.");

        return self::SUCCESS;
    }
}
