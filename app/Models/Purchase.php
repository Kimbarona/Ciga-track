<?php

namespace App\Models;

use App\Models\Concerns\Voidable;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory, LogsActivity, Voidable;

    protected $fillable = ['date', 'product_id', 'qty', 'unit_cost', 'supplier', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'qty' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Purchase $purchase) {
            $purchase->total_cost = round($purchase->qty * (float) $purchase->unit_cost, 2);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'product_id', 'qty', 'unit_cost', 'total_cost', 'supplier', 'notes', 'voided_at', 'void_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
