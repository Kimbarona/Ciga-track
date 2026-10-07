<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Actions\VoidAction;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Transactions';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expense')
                ->columns(2)
                ->schema([
                    DatePicker::make('date')->required()->default(now())->maxDate(now())->native(false)->displayFormat('M d, Y'),
                    Select::make('expense_category_id')
                        ->label('Category')
                        ->options(fn () => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->createOptionForm([TextInput::make('name')->required()->unique('expense_categories', 'name')])
                        ->createOptionUsing(fn (array $data) => ExpenseCategory::query()->create($data)->id),
                    TextInput::make('description')->required()->maxLength(255),
                    TextInput::make('amount')->prefix('₱')->numeric()->minValue(0.01)->required(),
                    TextInput::make('paid_via')->label('Paid by / mode')->maxLength(255)->datalist(['Cash', 'GCash', 'Maya', 'Bank Transfer']),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'creator']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('M d, Y')->sortable(),
                TextColumn::make('category.name')->label('Category')->badge()->color('gray'),
                TextColumn::make('description')->searchable()->wrap(),
                TextColumn::make('amount')->money('PHP')->alignEnd()->sortable()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('paid_via')->label('Paid via'),
                TextColumn::make('creator.name')->label('Encoded by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voided_at')->label('Voided')->dateTime('M d, Y g:i A')
                    ->description(fn (Expense $record) => $record->void_reason)->color('danger')->toggleable(),
            ])
            ->recordClasses(fn (Expense $record) => $record->isVoided() ? 'opacity-50 line-through' : null)
            ->filters([
                DateRangeFilter::make(),
                SelectFilter::make('expense_category_id')->label('Category')->relationship('category', 'name')->preload(),
                PurchaseResource::voidedFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                VoidAction::make(fn (Expense $record, string $reason) => app(ExpenseService::class)->void($record, $reason, auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
