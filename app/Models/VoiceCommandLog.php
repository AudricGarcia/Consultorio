<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCommandLog extends Model
{
    protected $fillable = ['user_id', 'patient_id', 'transcription', 'corrected_transcription', 'intent', 'outcome'];

    protected function casts(): array
    {
        return ['transcription' => 'encrypted', 'corrected_transcription' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
