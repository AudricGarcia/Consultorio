<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentCatalog extends Model
{
    protected $table = 'treatment_catalog';

    protected $fillable = ['code', 'name', 'description', 'base_price', 'is_active'];

    protected function casts(): array
    {
        return ['base_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function planItems(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class);
    }
}
