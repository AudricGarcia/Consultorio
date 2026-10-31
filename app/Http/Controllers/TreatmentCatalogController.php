<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTreatmentCatalogRequest;
use App\Models\AuditLog;
use App\Models\TreatmentCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TreatmentCatalogController extends Controller
{
    public function index(): View
    {
        $this->authorize('treatments.catalog.manage');
        $catalog = TreatmentCatalog::orderBy('name')->paginate(30);

        return view('treatment-catalog.index', compact('catalog'));
    }

    public function create(): View
    {
        $this->authorize('treatments.catalog.manage');

        return view('treatment-catalog.create', ['treatment' => new TreatmentCatalog]);
    }

    public function store(StoreTreatmentCatalogRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $treatment = TreatmentCatalog::create($data);
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'treatment_catalog.created', 'subject_type' => TreatmentCatalog::class, 'subject_id' => $treatment->id, 'after' => $treatment->only(['code', 'name', 'base_price', 'is_active']), 'ip_address' => $request->ip()]);

        return redirect()->route('treatment-catalog.index')->with('success', 'Procedimiento agregado al catálogo.');
    }

    public function edit(TreatmentCatalog $treatmentCatalog): View
    {
        $this->authorize('treatments.catalog.manage');

        return view('treatment-catalog.edit', ['treatment' => $treatmentCatalog]);
    }

    public function update(StoreTreatmentCatalogRequest $request, TreatmentCatalog $treatmentCatalog): RedirectResponse
    {
        $before = $treatmentCatalog->only(['code', 'name', 'base_price', 'is_active']);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $treatmentCatalog->update($data);
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'treatment_catalog.updated', 'subject_type' => TreatmentCatalog::class, 'subject_id' => $treatmentCatalog->id, 'before' => $before, 'after' => $treatmentCatalog->only(['code', 'name', 'base_price', 'is_active']), 'ip_address' => $request->ip()]);

        return redirect()->route('treatment-catalog.index')->with('success', 'Procedimiento actualizado.');
    }
}
