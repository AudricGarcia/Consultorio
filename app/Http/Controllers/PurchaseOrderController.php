<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('purchases.manage');
        $orders = PurchaseOrder::with(['supplier', 'creator:id,name', 'items'])->orderByDesc('created_at')->paginate(20);

        return view('purchases.index', compact('orders'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('purchases.manage');
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $items = InventoryItem::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']);

        return view('purchases.create', compact('suppliers', 'items'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);
        $order = DB::transaction(function () use ($data, $items, $request): PurchaseOrder {
            $order = PurchaseOrder::create([...$data, 'created_by' => $request->user()->id, 'status' => 'ordered', 'ordered_at' => today()]);
            $order->items()->createMany($items);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'purchase_order.created',
                'subject_type' => PurchaseOrder::class,
                'subject_id' => $order->id,
                'after' => ['supplier_id' => $order->supplier_id, 'item_count' => count($items), 'status' => $order->status],
                'ip_address' => $request->ip(),
            ]);

            return $order;
        });

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Orden de compra creada. La recepción de stock se registra por separado del pago.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('purchases.manage');
        $purchaseOrder->load(['supplier', 'creator:id,name', 'items.item', 'supplierPayments.receiver:id,name', 'supplierPayments.decider:id,name']);
        $total = $purchaseOrder->items->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_cost);
        $paid = $purchaseOrder->supplierPayments->where('status', 'paid')->sum(fn ($payment) => (float) $payment->amount);
        $reserved = $purchaseOrder->supplierPayments->where('status', 'pending_approval')->sum(fn ($payment) => (float) $payment->amount);

        return view('purchases.show', ['order' => $purchaseOrder, 'total' => $total, 'paid' => $paid, 'reserved' => $reserved, 'balance' => max(0, $total - $paid - $reserved), 'cashSession' => CashSession::where('opened_by', request()->user()->id)->where('status', 'open')->latest('opened_at')->first()]);
    }

    public function pay(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('purchases.pay');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'payment_method' => ['required', 'in:cash,card,transfer,yape,plin,other'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $pending = DB::transaction(function () use ($request, $purchaseOrder, $data): bool {
            $order = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($order->status !== 'received') {
                throw ValidationException::withMessages(['amount' => 'Solo se pueden pagar órdenes recibidas.']);
            }
            $session = CashSession::where('opened_by', $request->user()->id)->where('status', 'open')->lockForUpdate()->first();
            if (! $session) {
                throw ValidationException::withMessages(['amount' => 'Abre una caja antes de registrar el desembolso.']);
            }
            $total = $order->items->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_cost);
            $committed = (float) $order->supplierPayments()->whereIn('status', ['paid', 'pending_approval'])->sum('amount');
            if ((float) $data['amount'] > round($total - $committed, 2)) {
                throw ValidationException::withMessages(['amount' => 'El pago supera el saldo pendiente de la orden.']);
            }

            $pending = (float) $data['amount'] >= config('clinic.expense_approval_limit') && ! $request->user()->isOwner();
            $payment = SupplierPayment::create([
                'purchase_order_id' => $order->id, 'cash_session_id' => $session->id,
                'received_by' => $request->user()->id, 'amount' => $data['amount'],
                'payment_method' => $data['payment_method'], 'reference' => $data['reference'] ?? null,
                'status' => $pending ? 'pending_approval' : 'paid', 'paid_at' => $pending ? null : now(),
            ]);
            $movement = CashMovement::create([
                'cash_session_id' => $session->id, 'supplier_payment_id' => $payment->id,
                'recorded_by' => $request->user()->id, 'type' => 'expense', 'amount' => $data['amount'],
                'category' => 'supplier_payment', 'reference' => $data['reference'] ?? 'OC-'.$order->id,
                'reason' => 'Pago de orden de compra #'.$order->id,
                'status' => $pending ? 'pending_approval' : 'posted',
            ]);
            AuditLog::create(['user_id' => $request->user()->id, 'event' => $pending ? 'supplier_payment.pending_approval' : 'supplier_payment.created', 'subject_type' => SupplierPayment::class, 'subject_id' => $payment->id, 'after' => ['purchase_order_id' => $order->id, 'amount' => $payment->amount, 'status' => $payment->status, 'cash_movement_id' => $movement->id], 'ip_address' => $request->ip()]);

            return $pending;
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', $pending ? 'Pago registrado y pendiente de aprobación.' : 'Pago registrado en caja.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('purchases.manage');

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('info', 'Las órdenes emitidas se conservan; crea una orden complementaria para corregir cantidades.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('purchases.manage');
        abort(405, 'Las órdenes emitidas no se sobrescriben.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('purchases.manage');
        abort(405, 'Las órdenes emitidas se conservan como historial.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('purchases.receive');
        $data = $request->validate(['received_at' => ['required', 'date', 'before_or_equal:now']]);

        DB::transaction(function () use ($request, $purchaseOrder, $data): void {
            $order = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->with('items')->firstOrFail();

            if ($order->status !== 'ordered') {
                throw ValidationException::withMessages(['order' => 'La orden ya fue recibida o no está vigente.']);
            }

            foreach ($order->items as $line) {
                $item = InventoryItem::whereKey($line->inventory_item_id)->lockForUpdate()->firstOrFail();
                $batch = InventoryBatch::create([
                    'inventory_item_id' => $item->id,
                    'supplier_id' => $order->supplier_id,
                    'batch_number' => $line->batch_number,
                    'expires_on' => $line->expires_on,
                    'unit_cost' => $line->unit_cost,
                ]);
                InventoryMovement::create([
                    'inventory_item_id' => $item->id,
                    'inventory_batch_id' => $batch->id,
                    'recorded_by' => $request->user()->id,
                    'movement_type' => 'receipt',
                    'direction' => 'in',
                    'quantity' => $line->quantity,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $order->id,
                    'reason' => 'Recepción de orden de compra #'.$order->id,
                ]);
            }

            $order->update(['status' => 'received', 'received_at' => $data['received_at']]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'purchase_order.received',
                'subject_type' => PurchaseOrder::class,
                'subject_id' => $order->id,
                'after' => ['status' => 'received', 'received_at' => $data['received_at'], 'line_count' => $order->items->count()],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Orden recibida y movimientos de inventario registrados.');
    }
}
