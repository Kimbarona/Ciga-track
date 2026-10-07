<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Business details used on statements / PDFs and a few defaults. Super admin only.
 */
class AppSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'App Settings';

    protected static ?string $title = 'App Settings';

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-settings') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            ...collect(Setting::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => Setting::get($key)])->all(),
            'business_name' => Setting::businessName(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Business details')
                    ->description('Shown on reseller statements and PDF reports.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('business_name')->required()->maxLength(120),
                        TextInput::make('business_contact')->label('Contact')->maxLength(120),
                        Textarea::make('business_address')->label('Address')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('Defaults')
                    ->schema([
                        TextInput::make('default_reorder_level')
                            ->label('Default reorder level for new products (reams)')
                            ->integer()->minValue(0)->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([$this->getFormContentComponent()])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function getFormContentComponent(): EmbeddedSchema
    {
        return EmbeddedSchema::make('form');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value === null ? null : (string) $value);
        }

        activity('settings')->causedBy(auth()->user())->event('updated')->log('App settings updated');

        Notification::make()->success()->title('Settings saved')->send();
    }
}
