<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\Period;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait UsesDashboardPeriod
{
    use InteractsWithPageFilters;

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    protected function period(): array
    {
        return Period::range(
            $this->pageFilters['preset'] ?? 'this_month',
            $this->pageFilters['from'] ?? null,
            $this->pageFilters['to'] ?? null,
        );
    }

    protected function periodLabel(): string
    {
        return Period::label(
            $this->pageFilters['preset'] ?? 'this_month',
            $this->pageFilters['from'] ?? null,
            $this->pageFilters['to'] ?? null,
        );
    }
}
