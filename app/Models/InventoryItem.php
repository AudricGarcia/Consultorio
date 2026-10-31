<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = ['sku', 'name', 'category', 'unit', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return ['reorder_level' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
