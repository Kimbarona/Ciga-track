<?php

namespace App\Models;

use App\Enums\RemittanceMode;
use App\Enums\RemittanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Remittances are never voided; a wrong entry is rejected instead.
 */
class Remittance extends Model
{
    /** @use HasFactory<\Database\Factories\RemittanceFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date', 'reseller_id', 'amount', 'mode', 'reference_no', 'proof_path', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'mode' => RemittanceMode::class,
            'status' => RemittanceStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'date', 'reseller_id', 'amount', 'mode', 'reference_no', 'proof_path', 'status',
                'confirmed_by', 'confirmed_at', 'rejection_reason', 'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isPending(): bool
    {
        return $this->status === RemittanceStatus::Pending;
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), RemittanceStatus::Confirmed);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), RemittanceStatus::Pending);
    }
}
