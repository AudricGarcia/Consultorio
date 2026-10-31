<?php

namespace App\Models;

use Database\Factories\TreatmentPlanInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentPlanInstallment extends Model
{
    /** @use HasFactory<TreatmentPlanInstallmentFactory> */
    use HasFactory;

    protected $fillable = ['treatment_plan_id', 'installment_number', 'due_at', 'amount'];

    protected function casts(): array
    {
        return ['installment_number' => 'integer', 'due_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
