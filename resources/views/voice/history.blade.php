@extends('layouts.clinic')
@section('title', 'Historial de voz')
@section('content')
<header class="heading"><div><div class="kicker">Asistente virtual</div><h1>Historial de comandos</h1><div class="muted">Solo tú puedes ver tus transcripciones. Se eliminan automáticamente después de 90 días; el audio no se guarda.</div></div></header>
<section class="panel">
    <div class="tablewrap"><table>
        <thead><tr><th>Fecha</th><th>Intención</th><th>Resultado</th><th>Frase reconocida y corrección</th></tr></thead>
        <tbody>
            @forelse($commands as $command)
                <tr wire:key="voice-command-{{ $command->id }}">
                    <td>{{ $command->created_at->format('d/m/Y H:i') }}</td>
                    <td><span class="tag">{{ $command->intent }}</span></td>
                    <td>{{ $command->outcome === 'answered' ? 'Respondido' : 'No reconocido' }}</td>
                    <td>
                        <p><strong>Original:</strong> {{ $command->transcription }}</p>
                        <form method="POST" action="{{ route('assistant.voice-history.correct', $command) }}" class="grid">
                            @csrf @method('PUT')
                            <div class="field wide"><label for="correction-{{ $command->id }}">Corrección</label><input id="correction-{{ $command->id }}" name="corrected_transcription" maxlength="300" value="{{ old('corrected_transcription', $command->corrected_transcription ?? $command->transcription) }}" required></div>
                            <div class="field wide"><button class="btn small" type="submit">Guardar corrección</button><span class="hint">La corrección es solo para el historial; no vuelve a ejecutar la consulta.</span></div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty">Aún no hay comandos registrados.</div></td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $commands->links() }}</div>
</section>
@endsection
