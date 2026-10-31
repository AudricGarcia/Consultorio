<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = ['inventory_item_id', 'inventory_batch_id', 'recorded_by', 'movement_type', 'direction', 'quantity', 'reference_type', 'reference_id', 'treatment_material_consumption_id', 'reason'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'reason' => 'encrypted'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function materialConsumption(): BelongsTo
    {
        return $this->belongsTo(TreatmentMaterialConsumption::class, 'treatment_material_consumption_id');
    }
}
