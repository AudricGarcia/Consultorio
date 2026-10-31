<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalMedia extends Model
{
    protected $fillable = ['patient_id', 'clinical_record_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'];

    protected function casts(): array
    {
        return ['original_name' => 'encrypted', 'size' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
