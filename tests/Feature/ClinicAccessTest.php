<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalRecord;
use App\Models\DentalFinding;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PendingVoiceAction;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\TreatmentMaterialConsumption;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\VoiceCommandLog;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_owner_can_open_the_real_dashboard_after_signing_in(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get('/')->assertOk()->assertSee('Resumen de la clínica');
    }

    public function test_clinical_record_saves_encrypted_structured_diagnoses_with_tooth_and_certainty(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();

        $this->actingAs($owner)->post(route('clinical-records.store'), [
            'patient_id' => $patient->id,
            'encountered_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'record_type' => 'encounter',
            'structured_diagnoses' => [[
                'diagnosis' => 'Pulpitis reversible',
                'tooth_code' => '36',
                'certainty' => 'suspected',
                'notes' => 'Evaluar respuesta a pruebas térmicas',
            ]],
        ])->assertRedirect(route('patients.show', $patient));

        $diagnosis = ClinicalDiagnosis::firstOrFail();
        $this->assertSame('36', $diagnosis->tooth_code);
        $this->assertSame('Pulpitis reversible', $diagnosis->diagnosis);
        $this->assertNotSame('Pulpitis reversible', $diagnosis->getRawOriginal('diagnosis'));
        $this->assertNotSame('Evaluar respuesta a pruebas térmicas', $diagnosis->getRawOriginal('notes'));
        $this->get(route('clinical-records.show', $diagnosis->clinical_record_id))->assertOk()->assertSee('Diagnósticos estructurados')->assertSee('Pulpitis reversible')->assertSee('36');
    }

    public function test_owner_dashboard_shows_calculated_financial_and_operational_indicators(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Plan activo', 'status' => 'accepted', 'accepted_at' => now()]);
        TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Restauración', 'quantity' => 2, 'unit_price' => 500, 'status' => 'accepted']);
        Payment::create(['patient_id' => $patient->id, 'treatment_plan_id' => $plan->id, 'received_by' => $owner->id, 'amount' => 200, 'currency' => 'PEN', 'payment_method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
        Appointment::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'created_by' => $owner->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30), 'status' => 'scheduled']);
        $order = $this->receivedPurchaseOrder($owner, 1000);
        CashSession::create(['opened_by' => $owner->id, 'opened_at' => now(), 'opening_balance' => 500, 'status' => 'open']);

        $this->actingAs($owner)->post(route('purchase-orders.payments.store', $order), ['amount' => 300, 'payment_method' => 'cash'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk()->assertSee('Ingresos del mes')->assertSee('S/ 200.00')->assertSee('Egresos del mes')->assertSee('S/ 300.00')
            ->assertSee('Cuentas por cobrar')->assertSee('S/ 800.00')->assertSee('Cuentas por pagar')->assertSee('S/ 700.00')
            ->assertSee('Tratamientos activos')->assertSee('Citas sin confirmar · 48 h');
    }

    public function test_login_authenticates_and_redirects_to_the_dashboard(): void
    {
        $owner = $this->userWithRole('owner');
        $response = $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'Password1234']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_cashier_cannot_open_the_patient_directory(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->get(route('patients.index'))->assertForbidden();
    }

    public function test_cashier_can_search_for_payment_without_opening_the_clinical_directory(): void
    {
        $cashier = $this->userWithRole('cashier');
        $patient = Patient::factory()->create(['first_name' => 'Elena', 'last_name' => 'Soto', 'allergies' => 'Dato clínico reservado']);

        $this->actingAs($cashier)->get(route('payments.patients.search', ['q' => 'Elena']))->assertOk()->assertSee('Elena')->assertSee($patient->patient_code)->assertDontSee('Dato clínico reservado');
        $this->get(route('patients.payments.create', $patient))->assertOk()->assertSee('Registrar pago')->assertDontSee('Dato clínico reservado');
        $this->get(route('patients.show', $patient))->assertForbidden();
        $this->get(route('patients.index'))->assertForbidden();
    }

    public function test_cashier_can_record_a_patient_payment_into_their_open_cash_session(): void
    {
        $cashier = $this->userWithRole('cashier');
        $patient = Patient::factory()->create();
        CashSession::create(['opened_by' => $cashier->id, 'opened_at' => now(), 'opening_balance' => 100, 'status' => 'open']);

        $this->actingAs($cashier)->post(route('patients.payments.store', $patient), [
            'patient_id' => $patient->id, 'amount' => 150, 'payment_method' => 'cash', 'reference' => 'CAJA-1',
        ])->assertRedirect(route('payments.index'));
        $payment = Payment::firstOrFail();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'patient_id' => $patient->id, 'received_by' => $cashier->id, 'amount' => 150, 'status' => 'paid']);
        $this->assertDatabaseHas('cash_movements', ['payment_id' => $payment->id, 'recorded_by' => $cashier->id, 'type' => 'income', 'status' => 'posted']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'payment.created', 'subject_id' => $payment->id]);
    }

    public function test_payment_cannot_exceed_a_selected_plan_balance_but_can_be_recorded_as_an_advance(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Plan aceptado', 'status' => 'accepted', 'accepted_at' => now()]);
        TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Tratamiento', 'quantity' => 1, 'unit_price' => 500, 'status' => 'accepted']);
        Payment::create(['patient_id' => $patient->id, 'treatment_plan_id' => $plan->id, 'received_by' => $owner->id, 'amount' => 200, 'currency' => 'PEN', 'payment_method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
        CashSession::create(['opened_by' => $owner->id, 'opened_at' => now(), 'opening_balance' => 100, 'status' => 'open']);

        $this->actingAs($owner)->from(route('payments.patients.statement', $patient))->post(route('patients.payments.store', $patient), ['patient_id' => $patient->id, 'treatment_plan_id' => $plan->id, 'amount' => 301, 'payment_method' => 'cash'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 1);
        $this->post(route('patients.payments.store', $patient), ['patient_id' => $patient->id, 'treatment_plan_id' => $plan->id, 'amount' => 300, 'payment_method' => 'cash'])->assertRedirect(route('payments.index'));
        $this->post(route('patients.payments.store', $patient), ['patient_id' => $patient->id, 'amount' => 50, 'payment_method' => 'cash'])->assertRedirect(route('payments.index'));

        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseHas('payments', ['patient_id' => $patient->id, 'treatment_plan_id' => null, 'amount' => 50, 'status' => 'paid']);
    }

    public function test_patient_data_is_saved_encrypted_and_audited(): void
    {
        $owner = $this->userWithRole('owner');
        $payload = [
            'first_name' => 'Elena',
            'last_name' => 'Soto',
            'identity_document' => '12345678',
            'birth_date' => '1992-04-12',
            'phone' => '999123456',
            'email' => 'elena@example.test',
            'allergies' => 'Latex',
            'consent' => '1',
            'reminder_consent' => '1',
        ];

        $response = $this->actingAs($owner)->post(route('patients.store'), $payload);
        $patient = Patient::firstOrFail();

        $response->assertRedirect(route('patients.show', $patient));
        $this->assertSame('12345678', $patient->identity_document);
        $this->assertNotSame('12345678', $patient->getRawOriginal('identity_document'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'patient.created', 'subject_id' => $patient->id]);
        $this->assertDatabaseMissing('patients', ['identity_document' => '12345678']);
        $this->assertTrue($patient->reminder_consent);
        $this->assertNotNull($patient->reminder_consent_at);
    }

    public function test_patient_document_cannot_be_registered_twice(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner)->post(route('patients.store'), [
            'first_name' => 'Ana', 'last_name' => 'Ramos', 'identity_document' => '87654321', 'consent' => '1',
        ])->assertRedirect();

        $this->from(route('patients.create'))->post(route('patients.store'), [
            'first_name' => 'Ana María', 'last_name' => 'Ramos', 'identity_document' => '87654321', 'consent' => '1',
        ])->assertSessionHasErrors('identity_document');

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_clicking_a_tooth_filters_its_odontogram_history(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        DentalFinding::create(['patient_id' => $patient->id, 'tooth_code' => '36', 'surfaces' => ['occlusal'], 'finding_type' => 'caries', 'certainty' => 'confirmed', 'treatment_status' => 'recommended', 'recorded_by' => $owner->id]);
        DentalFinding::create(['patient_id' => $patient->id, 'tooth_code' => '46', 'surfaces' => ['mesial'], 'finding_type' => 'restoration', 'certainty' => 'confirmed', 'treatment_status' => 'performed', 'recorded_by' => $owner->id]);

        $this->actingAs($owner)->get(route('patients.odontogram.index', ['patient' => $patient, 'tooth' => '36']))
            ->assertOk()->assertSee('Odontograma gráfico')->assertSee('.tooth::before', false)
            ->assertSee('grid-template-columns:repeat(16', false)->assertSee('grid-template-columns:repeat(10', false)
            ->assertSee('18<small>Sin dato</small>', false)->assertSee('85<small>Sin dato</small>', false)
            ->assertSee('Historial de la pieza 36')->assertSee('caries')
            ->assertViewHas('findings', fn ($findings): bool => $findings->count() === 1 && $findings->first()->tooth_code === '36')
            ->assertSee(route('patients.odontogram.index', ['patient' => $patient, 'tooth' => 46]), false);
    }

    public function test_assistant_does_not_see_clinical_notes_in_patient_profile(): void
    {
        $assistant = $this->userWithRole('assistant');
        $patient = Patient::factory()->create(['medical_history' => 'Dato clínico privado 9081']);

        $this->actingAs($assistant)->get(route('patients.show', $patient))
            ->assertOk()
            ->assertDontSee('Dato clínico privado 9081');
    }

    public function test_voice_assistant_allows_authorized_read_only_patient_search(): void
    {
        $owner = $this->userWithRole('owner');
        Patient::factory()->create(['first_name' => 'Elena', 'last_name' => 'Soto', 'patient_code' => 'PAC-VOICE1']);

        $this->actingAs($owner)->postJson(route('assistant.voice'), ['command' => '¿Buscar paciente Elena Soto?'])
            ->assertOk()->assertJsonFragment(['message' => 'Resultados: Elena Soto (PAC-VOICE1)']);
    }

    public function test_voice_assistant_previews_and_confirms_a_dental_finding_for_the_active_patient(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create(['first_name' => 'Elena', 'last_name' => 'Soto']);
        $command = 'marcar posible caries en pieza 36 superficie oclusal';

        $this->actingAs($owner)->get(route('patients.odontogram.index', $patient))
            ->assertOk()->assertSee('id="voice-patient-id" type="hidden" value="'.$patient->id.'"', false)
            ->assertSee('Para registrar, dicta el hallazgo en esta ficha.');

        $preview = $this->postJson(route('assistant.voice'), ['command' => $command, 'patient_id' => $patient->id])
            ->assertOk()->assertJsonPath('requires_confirmation', true)
            ->assertJsonFragment(['message' => 'Paciente: Elena Soto. Hallazgo caries observado en la pieza 36 · superficie oclusal. Confirma para añadirlo al odontograma.']);

        $this->assertDatabaseCount('odontogram_findings', 0);
        $pendingAction = PendingVoiceAction::firstOrFail();
        $this->assertSame(['occlusal'], $pendingAction->payload['surfaces']);
        $this->assertNotSame('caries', $pendingAction->getRawOriginal('payload'));

        $payload = ['command' => $command, 'patient_id' => $patient->id, 'confirmation_token' => $preview->json('confirmation_token')];
        $this->postJson(route('assistant.voice'), $payload)->assertOk()
            ->assertJsonFragment(['message' => 'Hallazgo registrado en el odontograma, pieza 36.']);

        $finding = DentalFinding::firstOrFail();
        $this->assertSame(['occlusal'], $finding->surfaces);
        $this->assertSame('observed', $finding->certainty);
        $this->assertSame($owner->id, $finding->recorded_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'dental_finding.created_from_voice', 'subject_id' => $finding->id]);

        $this->postJson(route('assistant.voice'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('odontogram_findings', 1);
    }

    public function test_voice_assistant_requires_odontogram_permission_and_active_patient_context(): void
    {
        $assistant = $this->userWithRole('assistant');
        $patient = Patient::factory()->create();
        $command = 'marcar posible caries en pieza 36';

        $this->actingAs($assistant)->postJson(route('assistant.voice'), ['command' => $command, 'patient_id' => $patient->id])->assertForbidden();
        $this->assertDatabaseCount('pending_voice_actions', 0);

        $owner = $this->userWithRole('owner');
        $this->actingAs($owner)->postJson(route('assistant.voice'), ['command' => $command])
            ->assertOk()->assertJsonFragment(['message' => 'Abre primero la ficha del paciente y repite el dictado para asociar el hallazgo correcto.']);
        $this->assertDatabaseCount('pending_voice_actions', 0);
        $this->assertDatabaseCount('odontogram_findings', 0);
    }

    public function test_voice_dictation_requires_patient_consent_and_rechecks_it_before_saving(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create(['voice_assistant_consent' => false]);
        $command = 'marcar posible caries en pieza 36';

        $this->actingAs($owner)->postJson(route('assistant.voice'), [
            'command' => $command, 'patient_id' => $patient->id, 'source' => 'speech',
        ])->assertForbidden();
        $this->assertDatabaseCount('voice_command_logs', 0);
        $this->assertDatabaseCount('pending_voice_actions', 0);

        $patient->update(['voice_assistant_consent' => true]);
        $preview = $this->postJson(route('assistant.voice'), [
            'command' => $command, 'patient_id' => $patient->id, 'source' => 'speech',
        ])->assertOk()->assertJsonPath('requires_confirmation', true);
        $patient->update(['voice_assistant_consent' => false]);

        $this->postJson(route('assistant.voice'), [
            'command' => $command, 'confirmation_token' => $preview->json('confirmation_token'),
        ])->assertForbidden()->assertJsonFragment(['message' => 'Se retiró la autorización de voz del paciente. No se registró el hallazgo.']);
        $this->assertDatabaseCount('odontogram_findings', 0);
    }

    public function test_voice_history_encrypts_transcriptions_and_allows_non_executing_corrections(): void
    {
        $owner = $this->userWithRole('owner');
        Patient::factory()->create(['first_name' => 'Elena', 'last_name' => 'Soto', 'patient_code' => 'PAC-VOICE1']);

        $this->actingAs($owner)->postJson(route('assistant.voice'), ['command' => 'buscar paciente Elena Soto'])
            ->assertOk();

        $log = VoiceCommandLog::firstOrFail();
        $storedCiphertext = DB::table('voice_command_logs')->where('id', $log->id)->value('transcription');
        $this->assertNotSame('buscar paciente Elena Soto', $storedCiphertext);
        $this->assertSame('buscar paciente Elena Soto', $log->transcription);
        $this->assertSame('patient.search', $log->intent);

        $this->get(route('assistant.voice-history'))->assertOk()->assertSee('buscar paciente Elena Soto');
        $this->put(route('assistant.voice-history.correct', $log), ['corrected_transcription' => 'Buscar paciente Elena Soto'])
            ->assertRedirect(route('assistant.voice-history'));

        $this->assertSame('Buscar paciente Elena Soto', $log->fresh()->corrected_transcription);
        $this->assertDatabaseHas('audit_logs', ['event' => 'voice_command.corrected', 'subject_id' => $log->id]);
    }

    public function test_voice_history_and_corrections_are_private_to_the_command_owner(): void
    {
        $owner = $this->userWithRole('owner');
        $cashier = $this->userWithRole('cashier');
        $log = VoiceCommandLog::create(['user_id' => $owner->id, 'transcription' => 'texto confidencial', 'intent' => 'unknown', 'outcome' => 'not_recognized']);

        $this->actingAs($cashier)->get(route('assistant.voice-history'))->assertOk()->assertDontSee('texto confidencial');
        $this->put(route('assistant.voice-history.correct', $log), ['corrected_transcription' => 'texto corregido'])->assertNotFound();
    }

    public function test_voice_assistant_enforces_permissions_for_each_command(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->postJson(route('assistant.voice'), ['command' => 'buscar paciente Elena'])->assertForbidden();
    }

    public function test_voice_assistant_reads_only_the_authorized_patient_treatment_balance(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Soria']);
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Plan de tratamiento', 'status' => 'accepted', 'accepted_at' => now()]);
        $completedPlan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Tratamiento completado', 'status' => 'completed', 'accepted_at' => now()->subDay()]);
        TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Restauración', 'quantity' => 1, 'unit_price' => 500, 'status' => 'accepted']);
        TreatmentPlanItem::create(['treatment_plan_id' => $completedPlan->id, 'description' => 'Procedimiento realizado', 'quantity' => 1, 'unit_price' => 200, 'status' => 'completed']);
        Payment::create(['patient_id' => $patient->id, 'treatment_plan_id' => $plan->id, 'received_by' => $owner->id, 'amount' => 125, 'currency' => 'PEN', 'payment_method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($owner)->postJson(route('assistant.voice'), ['command' => 'consultar saldo pendiente de Ana Soria'])
            ->assertOk()->assertJsonPath('message', 'Saldo pendiente de tratamientos aceptados (incluye completados) para Ana Soria: S/ 575.00. No incluye presupuestos sin aceptar ni anticipos sin asociar a un plan.');
    }

    public function test_voice_assistant_rejects_balance_lookup_without_financial_permission(): void
    {
        $assistant = $this->userWithRole('assistant');

        $this->actingAs($assistant)->postJson(route('assistant.voice'), ['command' => 'consultar saldo pendiente de Ana Soria'])->assertForbidden();
    }

    public function test_voice_assistant_can_disambiguate_a_balance_lookup_by_patient_code(): void
    {
        $owner = $this->userWithRole('owner');
        Patient::factory()->create(['patient_code' => 'PAC-VOICE123', 'first_name' => 'Ana', 'last_name' => 'Soria']);

        $this->actingAs($owner)->postJson(route('assistant.voice'), ['command' => 'saldo pendiente de pac-voice123'])
            ->assertOk()->assertJsonPath('message', 'Saldo pendiente de tratamientos aceptados (incluye completados) para Ana Soria: S/ 0.00. No incluye presupuestos sin aceptar ni anticipos sin asociar a un plan.');
    }

    public function test_voice_assistant_asks_for_a_code_when_patient_balance_search_is_ambiguous(): void
    {
        $owner = $this->userWithRole('owner');
        Patient::factory()->count(2)->create(['first_name' => 'Ana', 'last_name' => 'Soria']);

        $this->actingAs($owner)->postJson(route('assistant.voice'), ['command' => 'saldo pendiente de Ana Soria'])
            ->assertOk()->assertJsonPath('message', 'Encontré más de un paciente. Indica el código de paciente para distinguirlo.');
    }

    public function test_assistant_cannot_write_clinical_fields_when_registering_patient(): void
    {
        $assistant = $this->userWithRole('assistant');
        $this->actingAs($assistant)->post(route('patients.store'), [
            'first_name' => 'Julia', 'last_name' => 'Paz', 'consent' => '1', 'medical_history' => 'Dato clínico no autorizado',
            'identity_document' => '99887766', 'address' => 'Dirección privada',
        ])->assertRedirect();

        $patient = Patient::where('first_name', 'Julia')->firstOrFail();
        $this->assertNull($patient->medical_history);
        $this->assertNull($patient->identity_document);
        $this->assertNull($patient->address);
    }

    public function test_owner_can_reopen_a_closed_cash_session_with_audited_reason(): void
    {
        $owner = $this->userWithRole('owner');
        $session = CashSession::create(['opened_by' => $owner->id, 'closed_by' => $owner->id, 'opened_at' => now()->subDay(), 'closed_at' => now()->subHour(), 'opening_balance' => 100, 'counted_balance' => 100, 'status' => 'closed']);

        $this->actingAs($owner)->post(route('cash.reopen', $session), ['reason' => 'Revisión autorizada del arqueo'])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_sessions', ['id' => $session->id, 'status' => 'open', 'closed_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cash_session.reopened', 'subject_id' => $session->id]);
    }

    public function test_owner_can_reject_another_users_pending_cash_expense(): void
    {
        $owner = $this->userWithRole('owner');
        $cashier = $this->userWithRole('cashier');
        $session = CashSession::create(['opened_by' => $cashier->id, 'opened_at' => now(), 'opening_balance' => 100, 'status' => 'open']);
        $movement = CashMovement::create(['cash_session_id' => $session->id, 'recorded_by' => $cashier->id, 'type' => 'expense', 'amount' => 6000, 'category' => 'Materiales', 'reason' => 'Compra propuesta', 'status' => 'pending_approval']);

        $this->actingAs($owner)->post(route('cash.expenses.reject', $movement), ['reason' => 'No se adjuntó comprobante'])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'status' => 'rejected', 'approved_by' => $owner->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'expense.rejected', 'subject_id' => $movement->id]);
    }

    public function test_clinical_image_is_private_and_access_is_permission_checked(): void
    {
        Storage::fake('local');
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $record = ClinicalRecord::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'encountered_at' => now(), 'record_type' => 'encounter', 'status' => 'draft']);

        $this->actingAs($owner)->post(route('clinical-records.media.store', $record), ['file' => UploadedFile::fake()->image('radiograph.png')])
            ->assertRedirect();
        $media = $record->media()->firstOrFail();
        Storage::disk('local')->assertExists($media->path);
        $this->assertNotSame('radiograph.png', $media->getRawOriginal('original_name'));

        $cashier = $this->userWithRole('cashier');
        $this->actingAs($cashier)->get(route('clinical-media.show', $media))->assertForbidden();
    }

    public function test_owner_can_open_financial_and_audit_reports(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('reports.financial'))->assertOk()->assertSee('Reporte financiero');
        $this->actingAs($owner)->get(route('reports.audit'))->assertOk()->assertSee('Registro de auditoría');
    }

    public function test_owner_can_record_partial_supplier_payment_and_cannot_overpay_order(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->receivedPurchaseOrder($owner, 1000);
        CashSession::create(['opened_by' => $owner->id, 'opened_at' => now(), 'opening_balance' => 500, 'status' => 'open']);

        $this->actingAs($owner)->post(route('purchase-orders.payments.store', $order), ['amount' => 400, 'payment_method' => 'transfer', 'reference' => 'TRX-1'])
            ->assertRedirect(route('purchase-orders.show', $order));
        $payment = $order->supplierPayments()->firstOrFail();
        $this->assertDatabaseHas('supplier_payments', ['id' => $payment->id, 'status' => 'paid', 'amount' => 400]);
        $this->assertDatabaseHas('cash_movements', ['supplier_payment_id' => $payment->id, 'status' => 'posted', 'amount' => 400, 'category' => 'supplier_payment']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'supplier_payment.created', 'subject_id' => $payment->id]);

        $this->from(route('purchase-orders.show', $order))->actingAs($owner)->post(route('purchase-orders.payments.store', $order), ['amount' => 601, 'payment_method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('supplier_payments', 1);
    }

    public function test_large_supplier_payment_waits_for_independent_approval(): void
    {
        $cashier = $this->userWithRole('cashier');
        $owner = $this->userWithRole('owner');
        $role = Role::where('name', 'cashier')->firstOrFail();
        $role->permissions()->attach(Permission::where('name', 'purchases.pay')->firstOrFail());
        $order = $this->receivedPurchaseOrder($owner, 8000);
        CashSession::create(['opened_by' => $cashier->id, 'opened_at' => now(), 'opening_balance' => 500, 'status' => 'open']);

        $this->actingAs($cashier)->post(route('purchase-orders.payments.store', $order), ['amount' => 6000, 'payment_method' => 'transfer'])
            ->assertRedirect(route('purchase-orders.show', $order));
        $payment = $order->supplierPayments()->firstOrFail();
        $this->assertDatabaseHas('supplier_payments', ['id' => $payment->id, 'status' => 'pending_approval', 'paid_at' => null]);
        $this->assertDatabaseHas('cash_movements', ['supplier_payment_id' => $payment->id, 'status' => 'pending_approval']);

        $movement = CashMovement::where('supplier_payment_id', $payment->id)->firstOrFail();
        $this->actingAs($owner)->post(route('cash.expenses.approve', $movement))->assertRedirect();
        $this->assertDatabaseHas('supplier_payments', ['id' => $payment->id, 'status' => 'paid', 'decided_by' => $owner->id]);
        $this->assertDatabaseHas('cash_movements', ['supplier_payment_id' => $payment->id, 'status' => 'posted', 'approved_by' => $owner->id]);
    }

    public function test_financial_report_calculates_receivables_from_accepted_plans_and_paid_amounts(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Restauración', 'status' => 'accepted', 'accepted_at' => now()->subDay()]);
        TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Resina', 'quantity' => 2, 'unit_price' => 500, 'status' => 'accepted']);
        Payment::create(['patient_id' => $patient->id, 'treatment_plan_id' => $plan->id, 'received_by' => $owner->id, 'amount' => 200, 'currency' => 'PEN', 'payment_method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
        $completedPlan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Finalizado con saldo', 'status' => 'completed', 'accepted_at' => now()->subDays(2)]);
        TreatmentPlanItem::create(['treatment_plan_id' => $completedPlan->id, 'description' => 'Procedimiento concluido', 'quantity' => 1, 'unit_price' => 500, 'status' => 'completed']);
        Payment::create(['patient_id' => $patient->id, 'treatment_plan_id' => $completedPlan->id, 'received_by' => $owner->id, 'amount' => 100, 'currency' => 'PEN', 'payment_method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($owner)->get(route('reports.financial'))->assertOk()->assertSee('Cuentas por cobrar')->assertSee('S/ 1,200.00');
    }

    public function test_financial_report_calculates_payables_from_received_orders_and_confirmed_payments(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->receivedPurchaseOrder($owner, 1000);
        CashSession::create(['opened_by' => $owner->id, 'opened_at' => now(), 'opening_balance' => 500, 'status' => 'open']);
        $this->actingAs($owner)->post(route('purchase-orders.payments.store', $order), ['amount' => 300, 'payment_method' => 'cash'])->assertRedirect();

        $this->get(route('reports.financial'))->assertOk()->assertSee('Cuentas por pagar')->assertSee('S/ 700.00');
    }

    public function test_plan_acceptance_creates_monthly_installments_whose_cents_sum_to_the_accepted_total(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Tratamiento por cuotas', 'status' => 'proposed']);
        TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Tratamiento', 'quantity' => 1, 'unit_price' => 1000.01, 'status' => 'proposed']);

        $this->actingAs($owner)->post(route('treatment-plans.accept', $plan), ['installment_count' => 3, 'first_due_at' => today()->toDateString()])->assertRedirect();

        $installments = $plan->installments()->orderBy('installment_number')->get();
        $this->assertCount(3, $installments);
        $this->assertSame(['333.34', '333.34', '333.33'], $installments->pluck('amount')->all());
        $this->assertSame(1000.01, round((float) $installments->sum('amount'), 2));
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $installments[1]->due_at->toDateString());
        $this->assertSame('accepted', $plan->fresh()->status);
    }

    public function test_plan_payment_allocates_oldest_installments_and_refund_restores_their_balances(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Tratamiento por cuotas', 'status' => 'proposed']);
        TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Tratamiento', 'quantity' => 1, 'unit_price' => 1000.01, 'status' => 'proposed']);
        $this->actingAs($owner)->post(route('treatment-plans.accept', $plan), ['installment_count' => 3, 'first_due_at' => today()->toDateString()])->assertRedirect();
        CashSession::create(['opened_by' => $owner->id, 'opened_at' => now(), 'opening_balance' => 0, 'status' => 'open']);

        $this->post(route('patients.payments.store', $patient), ['patient_id' => $patient->id, 'treatment_plan_id' => $plan->id, 'amount' => 500, 'payment_method' => 'cash'])->assertRedirect(route('payments.index'));
        $payment = Payment::firstOrFail();
        $allocations = $payment->allocations()->orderBy('id')->get();
        $this->assertSame(['333.34', '166.66'], $allocations->pluck('amount')->all());
        $this->get(route('payments.patients.statement', $patient))->assertOk()->assertSee('Cuotas · Presupuesto #'.$plan->id)->assertSee('S/ 333.34')->assertSee('S/ 166.66');

        $this->post(route('payments.refund', $payment), ['reason' => 'Devolución autorizada de prueba'])->assertRedirect();
        $this->get(route('payments.patients.statement', $patient))->assertOk()->assertSee('S/ 0.00')->assertSee('S/ 333.34');
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded']);
        $this->assertDatabaseCount('payment_allocations', 2);
    }

    public function test_cashier_can_view_a_limited_patient_statement_with_plan_balances_and_advances(): void
    {
        $owner = $this->userWithRole('owner');
        $cashier = $this->userWithRole('cashier');
        $patient = Patient::factory()->create(['first_name' => 'María', 'last_name' => 'Rojas', 'allergies' => 'Dato clínico privado']);
        $planOne = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Diagnóstico clínico privado', 'status' => 'accepted', 'accepted_at' => now()->subDay()]);
        $planTwo = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Tratamiento', 'status' => 'in_progress', 'accepted_at' => now()->subDay()]);
        $completedPlan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Procedimiento completado', 'status' => 'completed', 'accepted_at' => now()->subDays(2)]);
        TreatmentPlanItem::create(['treatment_plan_id' => $planOne->id, 'description' => 'Procedimiento', 'quantity' => 2, 'unit_price' => 500, 'status' => 'accepted']);
        TreatmentPlanItem::create(['treatment_plan_id' => $planTwo->id, 'description' => 'Control', 'quantity' => 1, 'unit_price' => 400, 'status' => 'in_progress']);
        TreatmentPlanItem::create(['treatment_plan_id' => $completedPlan->id, 'description' => 'Sesión final', 'quantity' => 1, 'unit_price' => 200, 'status' => 'completed']);
        foreach ([[$planOne->id, 250, 'paid'], [$planTwo->id, 100, 'paid'], [null, 80, 'paid'], [$planOne->id, 50, 'refunded']] as [$planId, $amount, $status]) {
            Payment::create(['patient_id' => $patient->id, 'treatment_plan_id' => $planId, 'received_by' => $owner->id, 'amount' => $amount, 'currency' => 'PEN', 'payment_method' => 'cash', 'status' => $status, 'paid_at' => now()]);
        }

        $this->actingAs($cashier)->get(route('payments.patients.statement', $patient))->assertOk()
            ->assertSee('Saldo por cobrar')->assertSee('S/ 1,250.00')->assertSee('S/ 430.00')
            ->assertSee('Anticipos sin asociar')->assertSee('S/ 80.00')->assertSee('S/ 50.00')
            ->assertSee('Presupuesto #'.$planOne->id)->assertDontSee('Dato clínico privado')->assertDontSee('Diagnóstico clínico privado');
    }

    public function test_user_without_financial_permission_cannot_open_patient_statement(): void
    {
        $assistant = $this->userWithRole('assistant');
        $patient = Patient::factory()->create();

        $this->actingAs($assistant)->get(route('payments.patients.statement', $patient))->assertForbidden();
    }

    public function test_owner_can_manage_treatment_catalog_but_cashier_cannot(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner)->post(route('treatment-catalog.store'), ['code' => 'RESINA', 'name' => 'Restauración con resina', 'base_price' => 250])
            ->assertRedirect(route('treatment-catalog.index'));
        $this->assertDatabaseHas('treatment_catalog', ['code' => 'RESINA', 'is_active' => true]);
        $this->get(route('treatment-plans.create'))->assertOk()->assertSee('Restauración con resina');

        $cashier = $this->userWithRole('cashier');
        $this->actingAs($cashier)->get(route('treatment-catalog.index'))->assertForbidden();
    }

    public function test_completed_treatment_session_is_encrypted_and_advances_plan_status(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Tratamiento', 'status' => 'proposed']);
        $item = TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Restauración', 'quantity' => 1, 'unit_price' => 250, 'status' => 'proposed']);
        $this->actingAs($owner)->post(route('treatment-plans.accept', $plan))->assertRedirect();
        $finding = DentalFinding::create(['patient_id' => $patient->id, 'tooth_code' => '36', 'surfaces' => ['occlusal', 'mesial'], 'finding_type' => 'caries', 'certainty' => 'confirmed', 'treatment_status' => 'accepted', 'description' => 'Lesión confirmada', 'recorded_by' => $owner->id]);

        $this->post(route('treatment-plans.sessions.store', [$plan, $item]), ['session_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'evolution' => 'Procedimiento realizado sin complicaciones', 'complete_item' => '1', 'dental_finding_id' => $finding->id, 'tooth_code' => '36', 'surfaces' => ['occlusal', 'mesial']])->assertRedirect();
        $session = TreatmentSession::firstOrFail();
        $this->assertNotSame('Procedimiento realizado sin complicaciones', $session->getRawOriginal('evolution'));
        $this->assertSame('36', $session->tooth_code);
        $this->assertSame(['occlusal', 'mesial'], $session->surfaces);
        $this->assertSame($finding->id, $session->dental_finding_id);
        $this->assertSame('completed', $item->fresh()->status);
        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'treatment_session.created', 'subject_id' => $session->id]);
        $this->get(route('treatment-plans.show', $plan))->assertOk()->assertSee('36')->assertSee('Ver hallazgo asociado');
        $this->get(route('patients.odontogram.show', [$patient, $finding]))->assertOk()->assertSee('Sesiones asociadas a este hallazgo')->assertSee('Restauración');
    }

    public function test_treatment_session_cannot_link_a_dental_finding_from_another_patient(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Tratamiento', 'status' => 'accepted', 'accepted_at' => now()]);
        $item = TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Restauración', 'quantity' => 1, 'unit_price' => 250, 'status' => 'in_progress']);
        $finding = DentalFinding::create(['patient_id' => $otherPatient->id, 'tooth_code' => '36', 'surfaces' => ['occlusal'], 'finding_type' => 'caries', 'certainty' => 'confirmed', 'treatment_status' => 'accepted', 'recorded_by' => $owner->id]);

        $this->actingAs($owner)->post(route('treatment-plans.sessions.store', [$plan, $item]), ['session_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'evolution' => 'Evolución de prueba', 'dental_finding_id' => $finding->id])
            ->assertNotFound();
        $this->assertDatabaseCount('treatment_sessions', 0);
    }

    public function test_treatment_session_rejects_surfaces_without_a_tooth(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Tratamiento', 'status' => 'accepted', 'accepted_at' => now()]);
        $item = TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Restauración', 'quantity' => 1, 'unit_price' => 250, 'status' => 'in_progress']);

        $this->actingAs($owner)->post(route('treatment-plans.sessions.store', [$plan, $item]), ['session_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'evolution' => 'Evolución de prueba', 'surfaces' => ['occlusal']])
            ->assertSessionHasErrors('tooth_code');
        $this->assertDatabaseCount('treatment_sessions', 0);
    }

    public function test_session_material_consumption_uses_fefo_and_is_idempotent(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'title' => 'Restauración', 'status' => 'in_progress', 'accepted_at' => now()]);
        $planItem = TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'description' => 'Resina', 'quantity' => 1, 'unit_price' => 250, 'status' => 'in_progress']);
        $session = TreatmentSession::create(['treatment_plan_item_id' => $planItem->id, 'professional_id' => $owner->id, 'session_at' => now()->subMinute(), 'evolution' => 'Sesión realizada', 'status' => 'completed']);
        $material = InventoryItem::create(['sku' => 'RES-FEFO', 'name' => 'Resina dental', 'category' => 'Restauración', 'unit' => 'ml', 'reorder_level' => 1, 'is_active' => true]);
        $soon = InventoryBatch::create(['inventory_item_id' => $material->id, 'batch_number' => 'PRONTO', 'expires_on' => today()->addDays(5), 'unit_cost' => 1]);
        $later = InventoryBatch::create(['inventory_item_id' => $material->id, 'batch_number' => 'LUEGO', 'expires_on' => today()->addDays(40), 'unit_cost' => 1]);
        $expired = InventoryBatch::create(['inventory_item_id' => $material->id, 'batch_number' => 'VENCIDO', 'expires_on' => today()->subDay(), 'unit_cost' => 1]);
        foreach ([[$soon, 2], [$later, 5], [$expired, 100]] as [$batch, $quantity]) {
            InventoryMovement::create(['inventory_item_id' => $material->id, 'inventory_batch_id' => $batch->id, 'recorded_by' => $owner->id, 'movement_type' => 'receipt', 'direction' => 'in', 'quantity' => $quantity, 'reason' => 'Recepción de prueba']);
        }
        $payload = ['idempotency_key' => '3b241101-e2bb-4255-8caf-4136c566a962', 'inventory_item_id' => $material->id, 'quantity' => 4, 'reason' => 'Consumo clínico registrado'];

        $this->actingAs($owner)->post(route('treatment-sessions.materials.store', $session), $payload)->assertRedirect();
        $this->post(route('treatment-sessions.materials.store', $session), $payload)->assertSessionHas('success', 'Este consumo ya había sido registrado; no se duplicó.');

        $consumption = TreatmentMaterialConsumption::firstOrFail();
        $this->assertSame(2, $consumption->movements()->count());
        $this->assertSame($soon->id, $consumption->movements()->orderBy('id')->first()->inventory_batch_id);
        $this->assertEquals(2.0, (float) $consumption->movements()->orderBy('id')->first()->quantity);
        $this->assertSame(0, $consumption->movements()->where('inventory_batch_id', $expired->id)->count());
        $this->assertDatabaseCount('treatment_material_consumptions', 1);
        $this->get(route('treatment-plans.show', $plan))->assertOk()->assertSee('Materiales de la sesión del')->assertSee('Resina dental');

        $inventoryClerk = $this->userWithRole('inventory');
        $this->actingAs($inventoryClerk)->post(route('treatment-sessions.materials.store', $session), [...$payload, 'idempotency_key' => '4514d9d6-f2c7-45a3-9bb1-438c265c3f7e'])->assertForbidden();
    }

    public function test_dashboard_reports_expired_and_soon_to_expire_inventory_batches(): void
    {
        $owner = $this->userWithRole('owner');
        $item = InventoryItem::create(['sku' => 'EXPIRY-01', 'name' => 'Anestésico', 'category' => 'Clínico', 'unit' => 'unid.', 'reorder_level' => 1, 'is_active' => true]);
        foreach ([today()->subDay(), today()->addDays(10)] as $index => $expiry) {
            $batch = InventoryBatch::create(['inventory_item_id' => $item->id, 'batch_number' => 'FECHA-'.$index, 'expires_on' => $expiry, 'unit_cost' => 1]);
            InventoryMovement::create(['inventory_item_id' => $item->id, 'inventory_batch_id' => $batch->id, 'recorded_by' => $owner->id, 'movement_type' => 'receipt', 'direction' => 'in', 'quantity' => 2, 'reason' => 'Recepción controlada']);
        }

        $this->actingAs($owner)->get('/')->assertOk()->assertSee('1 por vencer · 1 vencidos');
    }

    public function test_scheduler_creates_internal_reminders_once_and_cancels_them_for_cancelled_appointments(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $startsAt = now()->addHours(47)->addMinutes(30);
        $appointment = Appointment::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'created_by' => $owner->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addMinutes(30), 'status' => 'scheduled']);

        $this->artisan('appointments:queue-internal-reminders')->assertExitCode(0);
        $this->artisan('appointments:queue-internal-reminders')->assertExitCode(0);
        $this->assertDatabaseCount('appointment_reminders', 1);
        $this->assertDatabaseHas('appointment_reminders', ['appointment_id' => $appointment->id, 'reminder_offset_hours' => 48, 'status' => 'pending']);

        $appointment->update(['status' => 'cancelled']);
        $this->artisan('appointments:queue-internal-reminders')->assertExitCode(0);
        $this->assertDatabaseHas('appointment_reminders', ['appointment_id' => $appointment->id, 'status' => 'cancelled']);

        $rescheduledAt = now()->addHours(23)->addMinutes(30);
        $appointment->update(['status' => 'scheduled', 'starts_at' => $rescheduledAt, 'ends_at' => $rescheduledAt->copy()->addMinutes(30)]);
        $this->artisan('appointments:queue-internal-reminders')->assertExitCode(0);
        $this->assertDatabaseHas('appointment_reminders', ['appointment_id' => $appointment->id, 'reminder_offset_hours' => 24, 'status' => 'pending']);
    }

    public function test_staff_can_acknowledge_an_internal_reminder_without_marking_patient_contacted(): void
    {
        $owner = $this->userWithRole('owner');
        $patient = Patient::factory()->create();
        $startsAt = now()->addHours(2);
        $appointment = Appointment::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'created_by' => $owner->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addMinutes(30), 'status' => 'scheduled']);
        $reminder = AppointmentReminder::create(['appointment_id' => $appointment->id, 'reminder_offset_hours' => 2, 'scheduled_for' => now(), 'status' => 'pending']);

        $this->actingAs($owner)->post(route('appointment-reminders.acknowledge', $reminder))->assertRedirect();

        $this->assertDatabaseHas('appointment_reminders', ['id' => $reminder->id, 'status' => 'acknowledged', 'acknowledged_by' => $owner->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'appointment_reminder.acknowledged', 'subject_id' => $reminder->id]);
    }

    public function test_agenda_supports_day_week_and_month_ranges(): void
    {
        $owner = $this->userWithRole('owner');
        $weekStart = now()->startOfWeek()->setTime(9, 0);
        foreach ([['Lina', $weekStart], ['Marta', $weekStart->copy()->addDay()]] as [$name, $startsAt]) {
            $patient = Patient::factory()->create(['first_name' => $name]);
            Appointment::create(['patient_id' => $patient->id, 'professional_id' => $owner->id, 'created_by' => $owner->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addMinutes(30), 'status' => 'scheduled']);
        }

        $this->actingAs($owner)->get(route('appointments.index', ['date' => $weekStart->toDateString(), 'view' => 'week']))
            ->assertOk()->assertSee('Lina')->assertSee('Marta');
        $this->get(route('appointments.index', ['date' => $weekStart->toDateString(), 'view' => 'day']))
            ->assertOk()->assertSee('Lina')->assertDontSee('Marta');
        $this->get(route('appointments.index', ['date' => $weekStart->toDateString(), 'view' => 'month']))
            ->assertOk()->assertSee('Lina')->assertSee('Marta');
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['password' => 'Password1234']);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function receivedPurchaseOrder(User $owner, float $total): PurchaseOrder
    {
        $supplier = Supplier::create(['name' => 'Proveedor de prueba']);
        $item = InventoryItem::create(['sku' => 'TEST-MATERIAL', 'name' => 'Material de prueba', 'category' => 'material', 'unit' => 'unidad', 'reorder_level' => 1]);
        $order = PurchaseOrder::create(['supplier_id' => $supplier->id, 'created_by' => $owner->id, 'status' => 'received', 'ordered_at' => today(), 'received_at' => today()]);
        PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'inventory_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => $total]);

        return $order;
    }
}
