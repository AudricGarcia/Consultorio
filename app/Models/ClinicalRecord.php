<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'professional_id', 'encountered_at', 'record_type', 'reason',
        'anamnesis', 'examination', 'diagnoses', 'plan', 'evolution', 'status',
        'validated_by', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'anamnesis' => 'encrypted',
            'examination' => 'encrypted',
            'diagnoses' => 'encrypted',
            'plan' => 'encrypted',
            'evolution' => 'encrypted',
            'encountered_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function dentalFindings(): HasMany
    {
        return $this->hasMany(DentalFinding::class);
    }

    public function structuredDiagnoses(): HasMany
    {
        return $this->hasMany(ClinicalDiagnosis::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ClinicalMedia::class);
    }
}
