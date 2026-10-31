<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentSession extends Model
{
    protected $fillable = ['treatment_plan_item_id', 'professional_id', 'appointment_id', 'dental_finding_id', 'tooth_code', 'surfaces', 'session_at', 'evolution', 'status'];

    protected function casts(): array
    {
        return ['session_at' => 'datetime', 'evolution' => 'encrypted', 'surfaces' => 'array'];
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function dentalFinding(): BelongsTo
    {
        return $this->belongsTo(DentalFinding::class);
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(TreatmentMaterialConsumption::class);
    }
}
