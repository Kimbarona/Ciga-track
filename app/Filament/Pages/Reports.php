<?php

namespace App\Filament\Pages;

use App\Models\Reseller;
use App\Support\Period;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Reseller statement, P&L by period and stock movement — preview, print, PDF and Excel.
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.reports';

    /**
     * @var array<string, mixed>
     */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view-reports') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'report' => request()->query('report', 'statement'),
            'reseller_id' => request()->query('reseller') ?? Reseller::query()->where('type', 'reseller')->orderBy('name')->value('id'),
            'preset' => 'all',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
                    ->schema([
                        Select::make('report')
                            ->options([
                                'statement' => 'Reseller statement',
                                'pnl' => 'Profit & loss by period',
                                'stock' => 'Stock movement by product',
                            ])
                            ->selectablePlaceholder(false)
                            ->live(),
                        Select::make('reseller_id')
                            ->label('Reseller')
                            ->options(fn () => Reseller::withTrashed()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn (Get $get) => $get('report') === 'statement')
                            ->live(),
                        Select::make('preset')
                            ->label('Period')
                            ->options(Period::PRESETS)
                            ->selectablePlaceholder(false)
                            ->live(),
                        DatePicker::make('from')->native(false)->displayFormat('M d, Y')->live()
                            ->visible(fn (Get $get) => $get('preset') === 'custom'),
                        DatePicker::make('to')->native(false)->displayFormat('M d, Y')->live()
                            ->visible(fn (Get $get) => $get('preset') === 'custom'),
                    ]),
            ]);
    }

    public function reportUrl(?string $format = null): ?string
    {
        $f = $this->filters ?? [];
        $query = array_filter([
            'preset' => $f['preset'] ?? 'all',
            'from' => ($f['preset'] ?? null) === 'custom' ? ($f['from'] ?? null) : null,
            'to' => ($f['preset'] ?? null) === 'custom' ? ($f['to'] ?? null) : null,
            'format' => $format,
        ]);

        return match ($f['report'] ?? 'statement') {
            'statement' => filled($f['reseller_id'] ?? null)
                ? route('admin.reports.statement', ['reseller' => $f['reseller_id'], ...$query])
                : null,
            'pnl' => route('admin.reports.pnl', $query),
            'stock' => route('admin.reports.stock', $query),
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Open / Print')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => $this->reportUrl())
                ->openUrlInNewTab(),
            Action::make('pdf')
                ->label('PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn () => $this->reportUrl('pdf')),
            Action::make('xlsx')
                ->label('Excel (.xlsx)')
                ->icon(Heroicon::OutlinedTableCells)
                ->url(fn () => $this->reportUrl('xlsx')),
        ];
    }
}
