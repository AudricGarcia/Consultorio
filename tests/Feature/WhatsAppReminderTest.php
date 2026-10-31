<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppReminderTest extends TestCase
{
    public function test_reminders_are_never_sent_while_whatsapp_is_disabled(): void
    {
        $this->reminder();
        Http::fake();
        config(['services.whatsapp.enabled' => false]);

        $this->artisan('appointments:send-reminders')->assertExitCode(0)->expectsOutputToContain('desactivado');

        Http::assertNothingSent();
        $this->assertDatabaseHas('appointment_reminders', ['whatsapp_status' => 'pending']);
    }

    public function test_only_consented_patient_with_a_due_appointment_receives_an_approved_template(): void
    {
        $reminder = $this->reminder(['phone' => '+51988888888']);
        config($this->whatsAppConfiguration());
        Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test-123']]], 200)]);

        $this->artisan('appointments:send-reminders')->assertExitCode(0)->expectsOutputToContain('WhatsApp:');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.facebook.com/v99.0/123456789/messages'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '+51988888888'
            && $request['template']['name'] === 'recordatorio_cita'
            && $request['template']['components'][0]['parameters'][0]['text'] === config('app.name'));
        $this->assertDatabaseHas('appointment_reminders', ['id' => $reminder->id, 'whatsapp_status' => 'sent', 'whatsapp_message_id' => 'wamid.test-123']);
    }

    public function test_patient_without_recorded_consent_is_skipped_without_a_provider_request(): void
    {
        $reminder = $this->reminder(['reminder_consent' => false, 'reminder_consent_at' => null]);
        config($this->whatsAppConfiguration());
        Http::fake();

        $this->artisan('appointments:send-reminders')->assertExitCode(0)->expectsOutputToContain('1 omitido(s)');

        Http::assertNothingSent();
        $this->assertDatabaseHas('appointment_reminders', ['id' => $reminder->id, 'whatsapp_status' => 'skipped']);
    }

    public function test_unapproved_template_blocks_all_external_requests(): void
    {
        $reminder = $this->reminder();
        $configuration = $this->whatsAppConfiguration();
        $configuration['services.whatsapp']['template_approved'] = false;
        config($configuration);
        Http::fake();

        $this->artisan('appointments:send-reminders')->assertExitCode(1)->expectsOutputToContain('plantilla aprobada');

        Http::assertNothingSent();
        $this->assertDatabaseHas('appointment_reminders', ['id' => $reminder->id, 'whatsapp_status' => 'pending']);
    }

    public function test_failed_provider_response_is_not_automatically_retried(): void
    {
        $reminder = $this->reminder(['phone' => '+51988888888']);
        config($this->whatsAppConfiguration());
        Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'rejected']], 400)]);

        $this->artisan('appointments:send-reminders')->assertExitCode(0)->expectsOutputToContain('WhatsApp:');
        $this->assertDatabaseHas('appointment_reminders', ['id' => $reminder->id, 'whatsapp_status' => 'failed']);
        $this->artisan('appointments:send-reminders')->assertExitCode(0)->expectsOutputToContain('WhatsApp:');

        $this->assertCount(1, Http::recorded(fn ($request): bool => $request['to'] === '+51988888888'));
        $this->assertDatabaseHas('appointment_reminders', ['id' => $reminder->id, 'whatsapp_status' => 'failed']);
    }

    /** @param array<string, mixed> $patientOverrides */
    private function reminder(array $patientOverrides = []): AppointmentReminder
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(array_merge([
            'phone' => '+51999999999',
            'reminder_consent' => true,
            'reminder_consent_at' => now()->subDay(),
        ], $patientOverrides));
        $startsAt = now()->addHours(2);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'professional_id' => $user->id,
            'created_by' => $user->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'status' => 'confirmed',
        ]);

        return AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'appointment_starts_at' => $startsAt,
            'reminder_offset_hours' => 2,
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
        ]);
    }

    /** @return array<string, mixed> */
    private function whatsAppConfiguration(): array
    {
        return ['services.whatsapp' => [
            'enabled' => true,
            'template_approved' => true,
            'token' => 'test-secret',
            'phone_number_id' => '123456789',
            'graph_version' => 'v99.0',
            'template_name' => 'recordatorio_cita',
            'template_language' => 'es_PE',
        ]];
    }
}
