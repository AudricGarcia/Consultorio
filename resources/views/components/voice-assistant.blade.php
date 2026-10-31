@php($canRegisterDentalVoice = auth()->user()->can('odontogram.create') && isset($activePatient) && $activePatient)
@if(auth()->user()->can('patients.view') || auth()->user()->can('appointments.view') || auth()->user()->can('inventory.view') || $canRegisterDentalVoice)
<style>#voice-mic[hidden]{display:none!important}</style>
<style>.voicebox{position:fixed;right:20px;bottom:18px;z-index:20;width:min(355px,calc(100vw - 28px));background:#fff;border:1px solid #dce8e6;border-radius:14px;padding:14px;box-shadow:0 12px 40px #173d4025}.voicebox summary{cursor:pointer;font-size:12px;font-weight:700;color:#205b59}.voicebox .field{margin-top:12px}.voicebox output{display:block;margin-top:10px;font-size:12px;line-height:1.5;color:#355457;min-height:20px}.voicebox button{margin-top:9px}.voicebox .hint{display:block;margin-top:6px}</style>
@php($voicePlaceholder = $canRegisterDentalVoice ? 'Marca posible caries en pieza 36, superficie oclusal' : 'Ej.: saldo pendiente de Ana Pérez')
<details class="voicebox" id="voice-assistant"><summary>🎙 Asistente de voz</summary><form id="voice-form">@if($canRegisterDentalVoice)<input id="voice-patient-id" type="hidden" value="{{ $activePatient->id }}"><p class="hint">Paciente activo: <strong>{{ $activePatient->first_name }} {{ $activePatient->last_name }}</strong></p>@endif<div class="field"><label for="voice-command">Comando</label><input id="voice-command" maxlength="300" placeholder="{{ $voicePlaceholder }}" required></div><button class="btn primary" type="submit">Procesar comando</button> <button class="btn" type="button" id="voice-mic">Usar micrófono</button>@if($canRegisterDentalVoice)<button class="btn primary" type="button" id="voice-confirm" hidden>Confirmar y registrar</button><span class="hint">Para registrar, dicta el hallazgo en esta ficha. El sistema lo mostrará y pedirá confirmación antes de guardarlo.</span>@endif<span class="hint">La transcripción se cifra durante 90 días para revisar el historial. El audio no se almacena. El reconocimiento de voz depende del navegador y puede procesar el audio fuera de la clínica.</span><a class="hint" href="{{ route('assistant.voice-history') }}">Ver historial y corregir transcripciones</a><output id="voice-result" aria-live="polite"></output></form></details>
<script>
(() => {
    const form = document.getElementById('voice-form');
    const input = document.getElementById('voice-command');
    const output = document.getElementById('voice-result');
    @if($canRegisterDentalVoice && !$activePatient->voice_assistant_consent)
    document.getElementById('voice-mic').hidden = true;
    output.textContent = 'El paciente no autorizó el procesamiento de voz. Puedes escribir el hallazgo o registrar su autorización en la ficha.';
    @endif
    const patientInput = document.getElementById('voice-patient-id');
    const confirmButton = document.getElementById('voice-confirm');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let pendingConfirmationToken = null;
    let commandSource = 'typed';
    const sendCommand = async (confirmationToken = null) => {
        output.textContent = confirmationToken ? 'Registrando hallazgo…' : 'Procesando comando…';
        try {
            const payload = {command:input.value, source:commandSource};
            commandSource = 'typed';
            if (patientInput) { payload.patient_id = patientInput.value; }
            if (confirmationToken) { payload.confirmation_token = confirmationToken; }
            const response = await fetch(@json(route('assistant.voice')), {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});
            const data = await response.json(); output.textContent = data.message ?? data.errors?.command?.[0] ?? 'No se pudo procesar el comando.';
            if (data.requires_confirmation && confirmButton) { pendingConfirmationToken = data.confirmation_token; confirmButton.hidden = false; confirmButton.disabled = false; }
            else { pendingConfirmationToken = null; if (confirmButton) { confirmButton.hidden = true; confirmButton.disabled = false; } }
            if (window.speechSynthesis && response.ok) { speechSynthesis.cancel(); speechSynthesis.speak(new SpeechSynthesisUtterance(data.message)); }
        } catch (_) { output.textContent = 'No se pudo conectar con el sistema.'; }
    };
    form.addEventListener('submit', (event) => {
        event.preventDefault(); sendCommand();
    });
    input.addEventListener('input', () => { commandSource = 'typed'; pendingConfirmationToken = null; if (confirmButton) { confirmButton.hidden = true; } });
    confirmButton?.addEventListener('click', () => { if (pendingConfirmationToken) { confirmButton.disabled = true; sendCommand(pendingConfirmationToken); } });
    document.getElementById('voice-mic').addEventListener('click', () => {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) { output.textContent = 'Este navegador no tiene reconocimiento de voz. Puedes escribir el comando.'; return; }
        const recognition = new SpeechRecognition(); recognition.lang = 'es-PE'; recognition.interimResults = false;
        output.textContent = 'Escuchando…'; recognition.onresult = event => { input.value = event.results[0][0].transcript; commandSource = 'speech'; form.requestSubmit(); };
        recognition.onerror = () => { output.textContent = 'No se pudo reconocer la voz. Escribe el comando.'; }; recognition.start();
    });
})();
</script>
@endif
