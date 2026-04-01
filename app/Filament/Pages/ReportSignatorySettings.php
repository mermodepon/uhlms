<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReportSignatorySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Signatories Configuration';

    protected static ?string $navigationLabel = 'Signatories Configuration';

    protected static string $view = 'filament.pages.report-signatory-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'signatory_prepared_name'  => Setting::get('signatory_prepared_name',  'GENELYN ABARQUEZ – ENSOMO'),
            'signatory_prepared_title' => Setting::get('signatory_prepared_title', 'LODGING SUPERVISOR'),
            'signatory_approved_name'  => Setting::get('signatory_approved_name',  'RUBIE ANDOY - ARROYO'),
            'signatory_approved_title' => Setting::get('signatory_approved_title', 'Director, University Homestay'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Signatories')
                    ->description('Names and titles shown in the "Prepared by" and "Approved by" section of the printed Monthly Report.')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('signatory_prepared_name')
                            ->label('Prepared By — Name')
                            ->placeholder('e.g. GENELYN ABARQUEZ – ENSOMO')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('signatory_prepared_title')
                            ->label('Prepared By — Title / Position')
                            ->placeholder('e.g. LODGING SUPERVISOR')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('signatory_approved_name')
                            ->label('Approved By — Name')
                            ->placeholder('e.g. RUBIE ANDOY - ARROYO')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('signatory_approved_title')
                            ->label('Approved By — Title / Position')
                            ->placeholder('e.g. Director, University Homestay')
                            ->required()
                            ->maxLength(100),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save Changes')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $keys = [
            'signatory_prepared_name',
            'signatory_prepared_title',
            'signatory_approved_name',
            'signatory_approved_title',
        ];

        foreach ($keys as $key) {
            Setting::set($key, trim($data[$key] ?? ''));
        }

        Notification::make()
            ->title('Signatories updated successfully.')
            ->success()
            ->send();
    }
}
