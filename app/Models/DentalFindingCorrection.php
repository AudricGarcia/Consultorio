<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalFindingCorrection extends Model
{
    protected $fillable = ['original_finding_id', 'replacement_finding_id', 'corrected_by', 'reason'];

    protected function casts(): array
    {
        return ['reason' => 'encrypted'];
    }

    public function originalFinding(): BelongsTo
    {
        return $this->belongsTo(DentalFinding::class, 'original_finding_id');
    }

    public function replacementFinding(): BelongsTo
    {
        return $this->belongsTo(DentalFinding::class, 'replacement_finding_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
