<?php

namespace App\Http\Controllers;

use App\Models\AppointmentReminder;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentReminderController extends Controller
{
    public function acknowledge(Request $request, AppointmentReminder $appointmentReminder): RedirectResponse
    {
        $this->authorize('appointments.view');
        abort_unless($appointmentReminder->status === 'pending', 409);
        $appointment = $appointmentReminder->appointment;
        $this->authorize('view', $appointment->patient);
        abort_if($request->user()->hasRole('dentist') && (int) $appointment->professional_id !== (int) $request->user()->id, 403);

        $appointmentReminder->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'appointment_reminder.acknowledged', 'subject_type' => AppointmentReminder::class, 'subject_id' => $appointmentReminder->id, 'after' => ['appointment_id' => $appointment->id, 'offset_hours' => $appointmentReminder->reminder_offset_hours], 'ip_address' => $request->ip()]);

        return back()->with('success', 'Recordatorio interno marcado como revisado. No se ha contactado al paciente.');
    }
}
