<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalDiagnosis extends Model
{
    /** @use HasFactory<\Database\Factories\ClinicalDiagnosisFactory> */
    use HasFactory;

    protected $fillable = ['clinical_record_id', 'tooth_code', 'diagnosis', 'certainty', 'notes'];

    protected function casts(): array
    {
        return ['diagnosis' => 'encrypted', 'notes' => 'encrypted'];
    }

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }
}
