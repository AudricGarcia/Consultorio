<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $fillable = ['cash_session_id', 'payment_id', 'supplier_payment_id', 'recorded_by', 'type', 'amount', 'category', 'reference', 'reason', 'status', 'approved_by', 'approved_at', 'reversal_of'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reason' => 'encrypted', 'approved_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
