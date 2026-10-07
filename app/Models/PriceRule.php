<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * reseller_id = null  → default price for everyone
 * reseller_id = X     → special price for reseller X only
 *
 * Price changes are always new rows; a rule that a distribution has used is locked.
 */
class PriceRule extends Model
{
    /** @use HasFactory<\Database\Factories\PriceRuleFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['product_id', 'reseller_id', 'effective_date', 'reseller_price', 'retail_price', 'notes'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'reseller_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class)->withTrashed();
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function isDefault(): bool
    {
        return $this->reseller_id === null;
    }

    /**
     * A rule is locked once any distribution snapshot references it.
     */
    public function isLocked(): bool
    {
        return $this->distributions()->exists();
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->whereNull('reseller_id');
    }

    public function scopeSpecials(Builder $query): Builder
    {
        return $query->whereNotNull('reseller_id');
    }
}
