<?php

namespace App\Console\Commands;

use App\Models\AppointmentReminder;
use App\Services\WhatsAppCloudApi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('appointments:send-reminders')]
#[Description('Envía por WhatsApp solo recordatorios autorizados y con plantilla aprobada')]
class SendAppointmentReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WhatsAppCloudApi $whatsApp): int
    {
        if (! config('services.whatsapp.enabled')) {
            $this->components->warn('WhatsApp está desactivado. No se enviaron mensajes.');

            return self::SUCCESS;
        }

        if (! $whatsApp->isConfigured()) {
            $this->components->error('Falta una credencial válida o la confirmación de plantilla aprobada. No se enviaron mensajes.');

            return self::FAILURE;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        AppointmentReminder::query()->where('status', 'pending')->where('whatsapp_status', 'pending')
            ->where('scheduled_for', '<=', now())->with(['appointment.patient'])->orderBy('scheduled_for')
            ->chunkById(100, function ($reminders) use ($whatsApp, &$sent, &$skipped, &$failed): void {
                foreach ($reminders as $reminder) {
                    $appointment = $reminder->appointment;
                    $patient = $appointment?->patient;
                    if (! $appointment || ! $patient || ! in_array($appointment->status, ['scheduled', 'confirmed'], true)
                        || ! $patient->reminder_consent || ! $patient->reminder_consent_at
                        || ! $patient->phone || ! preg_match('/^\+[1-9]\d{7,14}$/', $patient->phone)
                        || ! $reminder->appointment_starts_at?->equalTo($appointment->starts_at)
                        || $appointment->starts_at->isPast()) {
                        $reminder->update(['whatsapp_status' => 'skipped']);
                        $skipped++;

                        continue;
                    }

                    try {
                        $messageId = $whatsApp->sendTemplate($patient->phone, [
                            config('app.name'),
                            $appointment->starts_at->timezone(config('app.timezone'))->translatedFormat('d/m/Y H:i'),
                        ]);
                        $reminder->update(['whatsapp_status' => 'sent', 'whatsapp_message_id' => $messageId, 'whatsapp_sent_at' => now()]);
                        $sent++;
                    } catch (Throwable) {
                        // Do not retry automatically: a timeout can occur after Meta accepted the message.
                        $reminder->update(['whatsapp_status' => 'failed']);
                        $failed++;
                    }
                }
            });

        $this->components->info("WhatsApp: {$sent} enviado(s), {$skipped} omitido(s), {$failed} fallido(s).");

        return self::SUCCESS;
    }
}
