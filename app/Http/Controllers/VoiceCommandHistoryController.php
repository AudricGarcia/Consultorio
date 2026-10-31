<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\VoiceCommandLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoiceCommandHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeVoiceUse($request);

        $commands = $request->user()->voiceCommandLogs()->latest()->paginate(25);

        return view('voice.history', compact('commands'));
    }

    public function correct(Request $request, VoiceCommandLog $voiceCommandLog): RedirectResponse
    {
        $this->authorizeVoiceUse($request);
        abort_unless($voiceCommandLog->user_id === $request->user()->id, 404);

        $data = $request->validate(['corrected_transcription' => ['required', 'string', 'max:300']]);
        $before = ['corrected' => filled($voiceCommandLog->corrected_transcription)];
        $voiceCommandLog->update(['corrected_transcription' => trim($data['corrected_transcription'])]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'event' => 'voice_command.corrected',
            'subject_type' => VoiceCommandLog::class,
            'subject_id' => $voiceCommandLog->id,
            'before' => $before,
            'after' => ['corrected' => true, 'intent' => $voiceCommandLog->intent],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('assistant.voice-history')->with('success', 'Corrección guardada. La frase corregida no ejecuta de nuevo el comando.');
    }

    private function authorizeVoiceUse(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->can('patients.view') || $user->can('appointments.view') || $user->can('inventory.view'), 403);
    }
}
