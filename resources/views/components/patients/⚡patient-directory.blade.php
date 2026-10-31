<?php

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $term = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('viewAny', Patient::class), 403);
    }

    public function updatedTerm(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $term = mb_substr(trim($this->term), 0, 100);
        $user = auth()->user();

        $patients = Patient::query()
            ->when($user->hasPermission('clinical.view') && ! $user->hasRole('owner'), function (Builder $query) use ($user): void {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('created_by', $user->id)
                        ->orWhereHas('appointments', fn (Builder $appointments) => $appointments->where('professional_id', $user->id))
                        ->orWhereHas('clinicalRecords', fn (Builder $records) => $records->where('professional_id', $user->id));
                });
            })
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('first_name', 'like', '%'.$term.'%')
                        ->orWhere('last_name', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%')
                        ->orWhere('patient_code', 'like', '%'.$term.'%');

                    if (preg_match('/^[0-9A-Za-z.-]+$/', $term) === 1) {
                        $documentHash = hash_hmac('sha256', mb_strtolower($term), (string) config('app.key'));
                        $search->orWhere('identity_document_hash', $documentHash);
                    }
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        return compact('patients', 'term');
    }
};
?>

<div class="space-y-4">
    <section class="panel">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <label class="sr-only" for="patient-search">Buscar pacientes</label>
            <input
                id="patient-search"
                class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-100"
                type="search"
                maxlength="100"
                placeholder="Nombre, DNI, teléfono o código"
                wire:model.live.debounce.300ms="term"
                autocomplete="off"
            >
            <span wire:loading wire:target="term" class="text-xs text-slate-500" role="status">Buscando…</span>
            @if($term !== '')
                <button class="btn" type="button" wire:click="$set('term', '')">Limpiar búsqueda</button>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="tablewrap">
            <table>
                <thead><tr><th>Código</th><th>Paciente</th><th>Teléfono</th><th>Correo</th><th></th></tr></thead>
                <tbody>
                    @forelse($patients as $patient)
                        <tr wire:key="patient-{{ $patient->id }}">
                            <td><span class="tag">{{ $patient->patient_code }}</span></td>
                            <td><strong>{{ $patient->last_name }}, {{ $patient->first_name }}</strong></td>
                            <td>{{ $patient->phone ?: '—' }}</td>
                            <td>{{ $patient->email ?: '—' }}</td>
                            <td><a class="btn small" href="{{ route('patients.show', $patient) }}">Ver ficha</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">@if($term)No se encontraron pacientes con esa búsqueda.@elseAún no hay pacientes registrados.@can('create', App\Models\Patient::class)<br><a href="{{ route('patients.create') }}">Registra el primer paciente</a>@endcan @endif</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $patients->links() }}</div>
    </section>
</div>
