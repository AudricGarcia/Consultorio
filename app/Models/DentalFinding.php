<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DentalFinding extends Model
{
    protected $table = 'odontogram_findings';

    protected $fillable = [
        'patient_id', 'clinical_record_id', 'tooth_code', 'surfaces', 'finding_type',
        'certainty', 'treatment_status', 'description', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['surfaces' => 'array', 'description' => 'encrypted'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function treatmentSessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class);
    }

    public function correction(): HasOne
    {
        return $this->hasOne(DentalFindingCorrection::class, 'original_finding_id');
    }

    public function correctedFrom(): HasOne
    {
        return $this->hasOne(DentalFindingCorrection::class, 'replacement_finding_id');
    }

    public function void(): HasOne
    {
        return $this->hasOne(DentalFindingVoid::class, 'dental_finding_id');
    }
}
