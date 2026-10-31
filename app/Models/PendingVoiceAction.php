<?php

namespace App\Models;

use Database\Factories\PendingVoiceActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingVoiceAction extends Model
{
    /** @use HasFactory<PendingVoiceActionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'patient_id', 'token_hash', 'payload', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
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
