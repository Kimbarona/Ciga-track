<?php

namespace App\Models\Concerns;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transactions are never deleted; they are voided with a reason instead.
 *
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by
 */
trait Voidable
{
    public function initializeVoidable(): void
    {
        $this->mergeCasts(['voided_at' => 'datetime']);
    }

    public function scopeNotVoided(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('voided_at'));
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('voided_at'));
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
