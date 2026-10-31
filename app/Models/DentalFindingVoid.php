<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalFindingVoid extends Model
{
    protected $fillable = ['dental_finding_id', 'voided_by', 'reason'];

    protected function casts(): array
    {
        return ['reason' => 'encrypted'];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(DentalFinding::class, 'dental_finding_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
