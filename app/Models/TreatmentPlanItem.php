<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentPlanItem extends Model
{
    protected $fillable = ['treatment_plan_id', 'treatment_catalog_id', 'description', 'quantity', 'unit_price', 'status'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(TreatmentCatalog::class, 'treatment_catalog_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class);
    }
}
