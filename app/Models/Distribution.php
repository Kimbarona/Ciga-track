<?php

namespace App\Models;

use App\Enums\PriceSource;
use App\Models\Concerns\Voidable;
use Database\Factories\DistributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * unit_price / price_source / price_rule_id are a SNAPSHOT taken when the row
 * is saved. They only change through an explicit edit or a confirmed re-price.
 */
class Distribution extends Model
{
    /** @use HasFactory<DistributionFactory> */
    use HasFactory, LogsActivity, Voidable;

    protected $fillable = [
        'date', 'reseller_id', 'product_id', 'qty', 'unit_price', 'price_source',
        'price_rule_id', 'override_reason', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'price_source' => PriceSource::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Distribution $distribution) {
            $distribution->total_amount = round($distribution->qty * (float) $distribution->unit_price, 2);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'date', 'reseller_id', 'product_id', 'qty', 'unit_price', 'total_amount', 'price_source',
                'price_rule_id', 'override_reason', 'notes', 'voided_at', 'void_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
