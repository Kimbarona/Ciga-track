<?php

namespace App\Models;

use App\Enums\ResellerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Reseller extends Model
{
    /** @use HasFactory<\Database\Factories\ResellerFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'contact', 'location', 'type', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'type' => ResellerType::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function isRetail(): bool
    {
        return $this->type === ResellerType::Retail;
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class);
    }

    public function specialPrices(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
