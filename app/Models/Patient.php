<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'patient_code', 'first_name', 'last_name', 'identity_document', 'identity_document_hash',
        'birth_date', 'phone', 'email', 'address', 'emergency_contact_name',
        'emergency_contact_phone', 'medical_history', 'allergies', 'medications',
        'dental_history', 'clinical_alerts', 'consent_at', 'reminder_consent', 'reminder_consent_at',
        'voice_assistant_consent', 'voice_assistant_consent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'identity_document' => 'encrypted',
            'address' => 'encrypted',
            'medical_history' => 'encrypted',
            'allergies' => 'encrypted',
            'medications' => 'encrypted',
            'dental_history' => 'encrypted',
            'clinical_alerts' => 'encrypted',
            'birth_date' => 'date',
            'consent_at' => 'datetime',
            'reminder_consent' => 'boolean',
            'reminder_consent_at' => 'datetime',
            'voice_assistant_consent' => 'boolean',
            'voice_assistant_consent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function clinicalRecords(): HasMany
    {
        return $this->hasMany(ClinicalRecord::class);
    }

    public function dentalFindings(): HasMany
    {
        return $this->hasMany(DentalFinding::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }
}
