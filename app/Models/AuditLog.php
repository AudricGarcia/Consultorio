<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'event', 'subject_type', 'subject_id', 'before', 'after', 'reason', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['before' => 'encrypted:array', 'after' => 'encrypted:array', 'reason' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
