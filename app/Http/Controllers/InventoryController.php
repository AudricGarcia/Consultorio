<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryMovementRequest;
use App\Http\Requests\StoreTreatmentMaterialConsumptionRequest;
use App\Models\AuditLog;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Models\TreatmentMaterialConsumption;
use App\Models\TreatmentSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('inventory.view');
        $items = $this->withStock()->orderBy('name')->paginate(25);

        return view('inventory.index', compact('items'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('inventory.manage');

        return view('inventory.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.manage');
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'unique:inventory_items,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:40'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);
        $item = InventoryItem::create($data);

        return redirect()->route('inventory.show', $item)->with('success', 'Producto agregado al catálogo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryItem $inventoryItem): View
    {
        $this->authorize('inventory.view');
        $inventoryItem->load([
            'batches' => fn ($query) => $query->select('inventory_batches.*')->selectSub(
                DB::table('inventory_movements')->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0)")->whereColumn('inventory_batch_id', 'inventory_batches.id'),
                'stock'
            )->with('supplier')->orderByRaw('CASE WHEN expires_on IS NULL THEN 1 ELSE 0 END')->orderBy('expires_on'),
            'movements' => fn ($query) => $query->with('recorder:id,name')->orderByDesc('created_at')->limit(30),
        ]);
        $stock = (float) $inventoryItem->movements()->where('direction', 'in')->sum('quantity')
            - (float) $inventoryItem->movements()->where('direction', 'out')->sum('quantity');
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('inventory.show', compact('inventoryItem', 'stock', 'suppliers'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InventoryItem $inventoryItem): View
    {
        $this->authorize('inventory.manage');

        return view('inventory.edit', compact('inventoryItem'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $this->authorize('inventory.manage');
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'unique:inventory_items,sku,'.$inventoryItem->id],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:40'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $inventoryItem->update($data);

        return redirect()->route('inventory.show', $inventoryItem)->with('success', 'Producto actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryItem $inventoryItem): RedirectResponse
    {
        $this->authorize('inventory.manage');
        $inventoryItem->update(['is_active' => false]);

        return redirect()->route('inventory.index')->with('success', 'Producto desactivado; su historial de stock se conserva.');
    }

    public function receive(Request $request): RedirectResponse
    {
        $this->authorize('inventory.receive');
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'reason' => ['required', 'string', 'min:6', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $item = InventoryItem::whereKey($data['inventory_item_id'])->lockForUpdate()->firstOrFail();
            $batch = InventoryBatch::create([
                'inventory_item_id' => $item->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'unit_cost' => $data['unit_cost'],
            ]);
            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'inventory_batch_id' => $batch->id,
                'recorded_by' => $request->user()->id,
                'movement_type' => 'receipt',
                'direction' => 'in',
                'quantity' => $data['quantity'],
                'reference_type' => Supplier::class,
                'reference_id' => $data['supplier_id'] ?? null,
                'reason' => $data['reason'],
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'inventory.received',
                'subject_type' => InventoryMovement::class,
                'subject_id' => $movement->id,
                'after' => ['item_id' => $item->id, 'batch_id' => $batch->id, 'quantity' => $movement->quantity],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('inventory.show', $data['inventory_item_id'])->with('success', 'Lote recibido y agregado al stock.');
    }

    public function move(StoreInventoryMovementRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data): void {
            $item = InventoryItem::whereKey($data['inventory_item_id'])->lockForUpdate()->firstOrFail();
            $currentStock = (float) $item->movements()->where('direction', 'in')->sum('quantity')
                - (float) $item->movements()->where('direction', 'out')->sum('quantity');

            if ($data['direction'] === 'out' && (float) $data['quantity'] > $currentStock) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad supera el stock disponible.']);
            }

            if (isset($data['inventory_batch_id']) && ! InventoryBatch::whereKey($data['inventory_batch_id'])->where('inventory_item_id', $item->id)->exists()) {
                throw ValidationException::withMessages(['inventory_batch_id' => 'El lote no corresponde a este producto.']);
            }

            if (($data['movement_type'] === 'receipt' && $data['direction'] !== 'in')
                || (in_array($data['movement_type'], ['consumption', 'damage', 'expiration'], true) && $data['direction'] !== 'out')) {
                throw ValidationException::withMessages(['movement_type' => 'El tipo de movimiento no coincide con el sentido de entrada o salida.']);
            }

            if ($data['direction'] === 'out' && isset($data['inventory_batch_id'])) {
                $batch = InventoryBatch::whereKey($data['inventory_batch_id'])->lockForUpdate()->firstOrFail();
                $batchStock = (float) $batch->movements()->where('direction', 'in')->sum('quantity')
                    - (float) $batch->movements()->where('direction', 'out')->sum('quantity');

                if ((float) $data['quantity'] > $batchStock) {
                    throw ValidationException::withMessages(['quantity' => 'La cantidad supera el stock de ese lote.']);
                }

                if ($data['movement_type'] === 'consumption' && $batch->expires_on?->lt(today())) {
                    throw ValidationException::withMessages(['inventory_batch_id' => 'No se puede consumir un lote vencido.']);
                }

                if ($data['movement_type'] === 'expiration' && ($batch->expires_on === null || $batch->expires_on->gte(today()))) {
                    throw ValidationException::withMessages(['inventory_batch_id' => 'Solo se pueden retirar como vencidos lotes cuya fecha ya pasó.']);
                }
            } elseif ($data['direction'] === 'out' && in_array($data['movement_type'], ['consumption', 'damage', 'expiration'], true)) {
                throw ValidationException::withMessages(['inventory_batch_id' => 'Selecciona un lote para validar su existencia y vencimiento.']);
            }

            $movement = InventoryMovement::create([...$data, 'recorded_by' => $request->user()->id]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'inventory.movement.created',
                'subject_type' => InventoryMovement::class,
                'subject_id' => $movement->id,
                'after' => ['item_id' => $item->id, 'type' => $movement->movement_type, 'direction' => $movement->direction, 'quantity' => $movement->quantity],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('inventory.show', $data['inventory_item_id'])->with('success', 'Movimiento de stock registrado.');
    }

    public function consumeForSession(StoreTreatmentMaterialConsumptionRequest $request, TreatmentSession $treatmentSession): RedirectResponse
    {
        $treatmentSession->load('planItem.plan.patient');
        $plan = $treatmentSession->planItem->plan;
        $this->authorize('view', $plan->patient);
        abort_unless($treatmentSession->status === 'completed', 409);
        abort_if($request->user()->hasRole('dentist') && (int) $treatmentSession->professional_id !== (int) $request->user()->id, 403);
        $data = $request->validated();

        $alreadyRecorded = DB::transaction(function () use ($request, $data, $treatmentSession, $plan): bool {
            $item = InventoryItem::whereKey($data['inventory_item_id'])->lockForUpdate()->firstOrFail();
            $existing = TreatmentMaterialConsumption::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                abort_unless($existing->treatment_session_id === $treatmentSession->id
                    && $existing->inventory_item_id === $item->id
                    && (float) $existing->quantity === (float) $data['quantity'], 409, 'La clave de esta solicitud ya se usó para otro consumo.');

                return true;
            }

            $batches = InventoryBatch::query()->where('inventory_item_id', $item->id)
                ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
                ->orderByRaw('CASE WHEN expires_on IS NULL THEN 1 ELSE 0 END')->orderBy('expires_on')->orderBy('id')
                ->lockForUpdate()->get();
            $allocations = [];
            $remaining = (float) $data['quantity'];
            foreach ($batches as $batch) {
                $available = (float) $batch->movements()->where('direction', 'in')->sum('quantity')
                    - (float) $batch->movements()->where('direction', 'out')->sum('quantity');
                if ($available <= 0) {
                    continue;
                }
                $taken = min($remaining, $available);
                $allocations[] = [$batch, $taken];
                $remaining = round($remaining - $taken, 3);
                if ($remaining <= 0) {
                    break;
                }
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages(['quantity' => 'No hay suficiente stock vigente y trazable por lotes.']);
            }

            $consumption = TreatmentMaterialConsumption::create([
                'idempotency_key' => $data['idempotency_key'],
                'treatment_session_id' => $treatmentSession->id,
                'inventory_item_id' => $item->id,
                'recorded_by' => $request->user()->id,
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
            ]);
            foreach ($allocations as [$batch, $quantity]) {
                InventoryMovement::create([
                    'inventory_item_id' => $item->id,
                    'inventory_batch_id' => $batch->id,
                    'recorded_by' => $request->user()->id,
                    'movement_type' => 'consumption',
                    'direction' => 'out',
                    'quantity' => $quantity,
                    'reference_type' => TreatmentSession::class,
                    'reference_id' => $treatmentSession->id,
                    'treatment_material_consumption_id' => $consumption->id,
                    'reason' => $data['reason'],
                ]);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'treatment_material.consumed', 'subject_type' => TreatmentMaterialConsumption::class, 'subject_id' => $consumption->id, 'after' => ['session_id' => $treatmentSession->id, 'plan_id' => $plan->id, 'item_id' => $item->id, 'quantity' => $data['quantity'], 'batch_ids' => collect($allocations)->map(fn ($allocation) => $allocation[0]->id)->all()], 'ip_address' => $request->ip()]);

            return false;
        });

        return back()->with('success', $alreadyRecorded ? 'Este consumo ya había sido registrado; no se duplicó.' : 'Material consumido por FEFO y vinculado a la sesión clínica.');
    }

    private function withStock(): Builder
    {
        return InventoryItem::query()->select('inventory_items.*')->selectSub(
            DB::table('inventory_movements')
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0)")
                ->whereColumn('inventory_movements.inventory_item_id', 'inventory_items.id'),
            'stock'
        );
    }
}
