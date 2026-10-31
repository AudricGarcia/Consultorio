<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DentalFinding;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\PendingVoiceAction;
use App\Models\TreatmentPlan;
use App\Models\VoiceCommandLog;
use App\Support\DentalFdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VoiceAssistantController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string', 'max:300'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'source' => ['nullable', Rule::in(['typed', 'speech'])],
            'confirmation_token' => ['nullable', 'string', 'size:64'],
        ]);
        $command = mb_strtolower(trim(preg_replace('/^[¿¡\s]+|[?!.\s]+$/u', '', $data['command']) ?? $data['command']));
        $user = $request->user();

        if (filled($data['confirmation_token'] ?? null)) {
            return $this->confirmDentalFinding($request, $data['command'], $data['confirmation_token']);
        }

        if (($finding = $this->parseDentalFinding($command)) !== null) {
            abort_unless($user->can('odontogram.create'), 403);

            if (empty($data['patient_id'])) {
                return $this->logResponse($request, $data['command'], 'dental_finding.create', response()->json([
                    'message' => 'Abre primero la ficha del paciente y repite el dictado para asociar el hallazgo correcto.',
                ]), outcome: 'needs_patient_context');
            }

            $patient = Patient::findOrFail($data['patient_id']);
            $this->authorize('view', $patient);
            $source = $data['source'] ?? 'typed';
            if ($source === 'speech' && ! $patient->voice_assistant_consent) {
                return response()->json(['message' => 'Este paciente no autorizó el procesamiento de voz. Escribe el hallazgo o registra su autorización en la ficha.'], 403);
            }
            $attributes = $this->validateVoiceFinding($finding);
            $attributes['source'] = $source;
            $token = str()->random(64);

            DB::transaction(function () use ($request, $data, $patient, $attributes, $token): void {
                PendingVoiceAction::query()->where(function ($query): void {
                    $query->where('expires_at', '<', now())->orWhere('consumed_at', '<', now()->subDay());
                })->delete();

                PendingVoiceAction::create([
                    'user_id' => $request->user()->id,
                    'patient_id' => $patient->id,
                    'token_hash' => hash('sha256', $token),
                    'payload' => $attributes,
                    'expires_at' => now()->addMinutes(2),
                ]);
                VoiceCommandLog::create([
                    'user_id' => $request->user()->id,
                    'patient_id' => $patient->id,
                    'transcription' => mb_substr(trim($data['command']), 0, 300),
                    'intent' => 'dental_finding.create',
                    'outcome' => 'pending_confirmation',
                ]);
            });

            $findingLabel = $this->findingLabel($attributes['finding_type']);
            $certaintyLabel = $attributes['certainty'] === 'confirmed' ? 'confirmado' : 'observado';
            $surfaceLabel = isset($attributes['surfaces'][0]) ? ' · superficie '.$this->surfaceLabel($attributes['surfaces'][0]) : '';

            return response()->json([
                'message' => 'Paciente: '.$patient->first_name.' '.$patient->last_name.'. Hallazgo '.$findingLabel.' '.$certaintyLabel.' en la pieza '.$attributes['tooth_code'].$surfaceLabel.'. Confirma para añadirlo al odontograma.',
                'requires_confirmation' => true,
                'confirmation_token' => $token,
            ]);
        }

        if (preg_match('/^(?:cu[aá]nto debe|consultar saldo(?: pendiente)? de|saldo pendiente de) (?:el paciente |la paciente |paciente )?(.+)$/u', $command, $matches) === 1) {
            abort_unless($user->can('payments.view') && ($user->can('patients.view') || $user->hasPermission('patients.payment_lookup')), 403);
            $name = trim($matches[1]);
            abort_if(preg_match('/^[\p{L}\p{N}\p{M} .\'-]{3,100}$/u', $name) !== 1, 422, 'Indica el nombre o código del paciente con letras y números.');
            $nameParts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name];
            $patients = Patient::query()
                ->when($user->hasRole('dentist') && ! $user->hasRole('owner'), fn ($query) => $query->where(function ($scope) use ($user): void {
                    $scope->where('created_by', $user->id)
                        ->orWhereHas('appointments', fn ($appointments) => $appointments->where('professional_id', $user->id))
                        ->orWhereHas('clinicalRecords', fn ($records) => $records->where('professional_id', $user->id));
                }))
                ->where(function ($query) use ($name, $nameParts): void {
                    $query->whereRaw('LOWER(patient_code) = ?', [mb_strtolower($name)])->orWhere(function ($names) use ($nameParts): void {
                        foreach ($nameParts as $part) {
                            $names->where(fn ($partQuery) => $partQuery->where('first_name', 'like', '%'.$part.'%')->orWhere('last_name', 'like', '%'.$part.'%'));
                        }
                    });
                })
                ->limit(2)->get(['id', 'first_name', 'last_name']);

            if ($patients->isEmpty()) {
                return $this->logResponse($request, $data['command'], 'patient.balance', response()->json(['message' => 'No encontré un paciente autorizado con ese nombre o código.']));
            }
            if ($patients->count() > 1) {
                return $this->logResponse($request, $data['command'], 'patient.balance', response()->json(['message' => 'Encontré más de un paciente. Indica el código de paciente para distinguirlo.']));
            }

            $patient = $patients->first();
            $planIds = TreatmentPlan::where('patient_id', $patient->id)->whereIn('status', ['accepted', 'in_progress', 'completed'])->whereNotNull('accepted_at')->pluck('id');
            $planned = $planIds->isEmpty() ? 0 : (float) DB::table('treatment_plan_items')->whereIn('treatment_plan_id', $planIds)->sum(DB::raw('quantity * unit_price'));
            $paid = $planIds->isEmpty() ? 0 : (float) DB::table('payments')->whereIn('treatment_plan_id', $planIds)->where('status', 'paid')->sum('amount');
            $balance = max(0, round($planned - $paid, 2));

            return $this->logResponse($request, $data['command'], 'patient.balance', response()->json(['message' => 'Saldo pendiente de tratamientos aceptados (incluye completados) para '.$patient->first_name.' '.$patient->last_name.': S/ '.number_format($balance, 2).'. No incluye presupuestos sin aceptar ni anticipos sin asociar a un plan.']), $patient->id);
        }

        if (preg_match('/^(buscar|busca|encuentra) paciente (.+)$/u', $command, $matches) === 1) {
            abort_unless($user->can('patients.view'), 403);
            $name = trim($matches[2]);
            abort_if(mb_strlen($name) < 3, 422, 'Indica al menos tres letras del nombre.');
            $nameParts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name];
            $patients = Patient::query()
                ->when($user->hasPermission('clinical.view') && ! $user->hasRole('owner'), fn ($query) => $query->where(function ($scope) use ($user): void {
                    $scope->where('created_by', $user->id)
                        ->orWhereHas('appointments', fn ($appointments) => $appointments->where('professional_id', $user->id))
                        ->orWhereHas('clinicalRecords', fn ($records) => $records->where('professional_id', $user->id));
                }))
                ->where(function ($query) use ($nameParts): void {
                    foreach ($nameParts as $part) {
                        $query->where(fn ($partQuery) => $partQuery->where('first_name', 'like', '%'.$part.'%')->orWhere('last_name', 'like', '%'.$part.'%'));
                    }
                })
                ->orderBy('last_name')->limit(5)->get(['id', 'patient_code', 'first_name', 'last_name']);

            return $this->logResponse($request, $data['command'], 'patient.search', response()->json(['message' => $patients->isEmpty()
                ? 'No encontré pacientes con ese nombre.'
                : 'Resultados: '.$patients->map(fn ($patient) => $patient->first_name.' '.$patient->last_name.' ('.$patient->patient_code.')')->implode('; ')]), $patients->count() === 1 ? $patients->first()->id : null);
        }

        if (preg_match('/^(mostrar|consultar|ver) citas (de )?(hoy|mañana)$/u', $command, $matches) === 1) {
            abort_unless($user->can('appointments.view'), 403);
            $date = $matches[3] === 'mañana' ? today()->addDay() : today();
            $appointments = Appointment::query()->with('patient:id,first_name,last_name')
                ->whereDate('starts_at', $date)->whereNotIn('status', ['cancelled', 'no_show'])
                ->when($user->hasRole('dentist'), fn ($query) => $query->where('professional_id', $user->id))
                ->orderBy('starts_at')->limit(10)->get();

            return $this->logResponse($request, $data['command'], 'appointments.list', response()->json(['message' => $appointments->isEmpty() ? 'No hay citas programadas.' : 'Citas: '.$appointments->map(fn ($item) => $item->starts_at->format('H:i').' con '.$item->patient->first_name.' '.$item->patient->last_name)->implode('; ')]));
        }

        if (preg_match('/^(cu[aá]nt[oa]s?|consultar|ver) (.+) (hay|tenemos|disponibles?)$/u', $command, $matches) === 1) {
            abort_unless($user->can('inventory.view'), 403);
            $term = trim($matches[2]);
            $items = InventoryItem::query()->where('is_active', true)->where('name', 'like', '%'.$term.'%')
                ->select('inventory_items.id', 'inventory_items.name', 'inventory_items.reorder_level')
                ->selectSub(DB::table('inventory_movements')->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0)")->whereColumn('inventory_movements.inventory_item_id', 'inventory_items.id'), 'stock')
                ->limit(5)->get();

            return $this->logResponse($request, $data['command'], 'inventory.stock', response()->json(['message' => $items->isEmpty() ? 'No encontré ese material en el catálogo.' : $items->map(fn ($item) => $item->name.': '.(float) $item->stock.' unidades')->implode('; ')]));
        }

        $message = 'No reconocí una consulta permitida. Puedes decir: buscar paciente Ana Pérez, consultar saldo pendiente de Ana Pérez, mostrar citas de mañana o cuántas resinas tenemos.';
        if ($user->can('odontogram.create')) {
            $message .= ' Desde la ficha del paciente también puedes dictar: marcar posible caries en pieza 36, superficie oclusal.';
        }

        return $this->logResponse($request, $data['command'], 'unknown', response()->json(['message' => $message]), outcome: 'not_recognized');
    }

    /** @return array{tooth_code: string, surfaces: list<string>, finding_type: string, certainty: string, treatment_status: string, description: null}|null */
    private function parseDentalFinding(string $command): ?array
    {
        $pattern = '/^(?:registrar|marcar|anotar)\s+(?:(posible|confirmada|confirmado)\s+)?(caries|restauraci[oó]n|ausencia(?: dental)?|fractura|corona|pr[oó]tesis|implante|endodoncia|periodontal|otro)\s+(?:en\s+)?(?:la\s+)?pieza\s+(\d{2})(?:[, ]+\s*(?:superficie|cara)\s+(oclusal|mesial|distal|vestibular|lingual|palatina|incisal))?$/u';
        if (preg_match($pattern, $command, $matches) !== 1) {
            return null;
        }

        $findingType = match ($matches[2]) {
            'caries' => 'caries',
            'restauración' => 'restoration',
            'ausencia', 'ausencia dental' => 'missing',
            'fractura' => 'fracture',
            'corona' => 'crown',
            'prótesis' => 'prosthesis',
            'implante' => 'implant',
            'endodoncia' => 'endodontics',
            'periodontal' => 'periodontal',
            default => 'other',
        };
        $surface = match ($matches[4] ?? null) {
            'oclusal' => 'occlusal',
            'palatina' => 'palatal',
            default => $matches[4] ?? null,
        };

        return [
            'tooth_code' => $matches[3],
            'surfaces' => $surface === null ? [] : [$surface],
            'finding_type' => $findingType,
            'certainty' => in_array($matches[1] ?? '', ['confirmada', 'confirmado'], true) ? 'confirmed' : 'observed',
            'treatment_status' => 'none',
            'description' => null,
        ];
    }

    /** @param array<string, mixed> $finding
     * @return array{tooth_code: string, surfaces: list<string>, finding_type: string, certainty: string, treatment_status: string, description: ?string}
     */
    private function validateVoiceFinding(array $finding): array
    {
        return Validator::make($finding, [
            'tooth_code' => ['required', 'string', Rule::in(DentalFdi::toothCodes())],
            'surfaces' => ['present', 'array', 'max:1'],
            'surfaces.*' => ['string', Rule::in(DentalFdi::surfaces())],
            'finding_type' => ['required', Rule::in(['caries', 'restoration', 'missing', 'fracture', 'crown', 'prosthesis', 'implant', 'endodontics', 'periodontal', 'other'])],
            'certainty' => ['required', Rule::in(['observed', 'confirmed'])],
            'treatment_status' => ['required', Rule::in(['none'])],
            'description' => ['nullable', 'string', 'max:3000'],
        ])->validate();
    }

    private function confirmDentalFinding(Request $request, string $transcription, string $token): JsonResponse
    {
        abort_unless($request->user()->can('odontogram.create'), 403);
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use ($request, $transcription, $tokenHash): JsonResponse {
            $pending = PendingVoiceAction::query()
                ->where('user_id', $request->user()->id)
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();
            abort_unless($pending !== null, 409, 'La confirmación expiró o ya fue utilizada. Dicta de nuevo el hallazgo.');
            abort_if($pending->consumed_at !== null || $pending->expires_at->isPast(), 409, 'La confirmación expiró o ya fue utilizada. Dicta de nuevo el hallazgo.');

            $patient = Patient::findOrFail($pending->patient_id);
            $this->authorize('view', $patient);
            $claimed = PendingVoiceAction::query()->whereKey($pending->id)->whereNull('consumed_at')->where('expires_at', '>', now())
                ->update(['consumed_at' => now(), 'updated_at' => now()]);
            abort_unless($claimed === 1, 409, 'La confirmación expiró o ya fue utilizada. Dicta de nuevo el hallazgo.');

            $payload = $pending->payload;
            if (($payload['source'] ?? 'typed') === 'speech' && ! $patient->voice_assistant_consent) {
                abort(403, 'Se retiró la autorización de voz del paciente. No se registró el hallazgo.');
            }
            unset($payload['source']);
            $attributes = $this->validateVoiceFinding($payload);
            $finding = DentalFinding::create([
                ...$attributes,
                'patient_id' => $patient->id,
                'recorded_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'dental_finding.created_from_voice',
                'subject_type' => DentalFinding::class,
                'subject_id' => $finding->id,
                'after' => [
                    'patient_id' => $finding->patient_id,
                    'tooth_code' => $finding->tooth_code,
                    'finding_type' => $finding->finding_type,
                    'certainty' => $finding->certainty,
                    'treatment_status' => $finding->treatment_status,
                ],
                'ip_address' => $request->ip(),
            ]);

            return $this->logResponse($request, $transcription, 'dental_finding.create', response()->json([
                'message' => 'Hallazgo registrado en el odontograma, pieza '.$finding->tooth_code.'.',
            ]), $patient->id, 'executed');
        });
    }

    private function findingLabel(string $findingType): string
    {
        return [
            'caries' => 'caries',
            'restoration' => 'restauración',
            'missing' => 'ausencia dental',
            'fracture' => 'fractura',
            'crown' => 'corona',
            'prosthesis' => 'prótesis',
            'implant' => 'implante',
            'endodontics' => 'endodoncia',
            'periodontal' => 'hallazgo periodontal',
            'other' => 'otro hallazgo',
        ][$findingType] ?? 'hallazgo';
    }

    private function surfaceLabel(string $surface): string
    {
        return [
            'occlusal' => 'oclusal',
            'mesial' => 'mesial',
            'distal' => 'distal',
            'vestibular' => 'vestibular',
            'lingual' => 'lingual',
            'palatal' => 'palatina',
            'incisal' => 'incisal',
        ][$surface] ?? $surface;
    }

    private function logResponse(Request $request, string $transcription, string $intent, JsonResponse $response, ?int $patientId = null, string $outcome = 'answered'): JsonResponse
    {
        VoiceCommandLog::create([
            'user_id' => $request->user()->id,
            'patient_id' => $patientId,
            'transcription' => mb_substr(trim($transcription), 0, 300),
            'intent' => $intent,
            'outcome' => $outcome,
        ]);

        return $response;
    }
}
