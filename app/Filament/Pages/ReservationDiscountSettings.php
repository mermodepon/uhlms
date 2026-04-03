<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\NotificationHelper;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReservationDiscountSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Discount Configuration';

    protected static ?string $navigationLabel = 'Discount Configuration';

    protected static string $view = 'filament.pages.reservation-discount-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->hasPermission('reservation_discount_settings_view') || $user?->hasPermission('reservation_discount_settings_edit')) ?? false;
    }

    public static function canEdit(): bool
    {
        return auth()->user()?->hasPermission('reservation_discount_settings_edit') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'discount_pwd_percent' => (float) Setting::get('discount_pwd_percent', 0),
            'discount_senior_percent' => (float) Setting::get('discount_senior_percent', 0),
            'discount_student_percent' => (float) Setting::get('discount_student_percent', 0),
        ]);
    }

    public function form(Form $form): Form
    {
        $readOnly = ! static::canEdit();

        return $form
            ->disabled($readOnly)
            ->schema([
                Forms\Components\Section::make('Guest Discount Rates')
                    ->description('Set percentage discounts used in check-in pricing calculations.')
                    ->icon('heroicon-o-calculator')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('discount_pwd_percent')
                            ->label('PWD Discount (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('discount_senior_percent')
                            ->label('Senior Citizen Discount (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('discount_student_percent')
                            ->label('Student Discount (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! static::canEdit()) {
            Notification::make()
                ->title('Access denied.')
                ->body('You do not have permission to edit discount configuration.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $keys = [
            'discount_pwd_percent',
            'discount_senior_percent',
            'discount_student_percent',
        ];

        $changed = false;
        foreach ($keys as $key) {
            $current = (string) Setting::get($key, '0');
            $new = (string) ((float) ($data[$key] ?? 0));
            if ($current !== $new) {
                $changed = true;
                break;
            }
        }

        Setting::withoutEvents(function () use ($data, $keys) {
            foreach ($keys as $key) {
                Setting::set($key, (string) ((float) ($data[$key] ?? 0)));
            }
        });

        if ($changed) {
            $actor = auth()->user();
            $actorName = $actor?->name ?? 'Someone';

            $recipients = User::whereIn('role', ['admin', 'staff'])
                ->where('id', '!=', $actor?->id)
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('reservation_discount_settings_view'))
                ->pluck('id')
                ->toArray();

            NotificationHelper::notifyUsers(
                $recipients,
                'Discount Configuration Updated',
                "{$actorName} updated reservation discount percentages.",
                'info',
                'setting',
                url('/admin/reservation-discount-settings')
            );

            NotificationHelper::notifyUser(
                $actor,
                'Discount Configuration Saved',
                'You updated reservation discount percentages.',
                'success',
                'setting',
                url('/admin/reservation-discount-settings')
            );
        }

        Notification::make()
            ->title('Discount configuration saved successfully.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        if (! static::canEdit()) {
            return [];
        }

        return [
            Action::make('save')
                ->label('Save Discount Configuration')
                ->submit('save')
                ->icon('heroicon-o-check')
                ->color('success'),
        ];
    }
}
