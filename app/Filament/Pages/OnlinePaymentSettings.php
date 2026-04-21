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

class OnlinePaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 21;

    protected static ?string $title = 'Online Payment Configuration';

    protected static ?string $navigationLabel = 'Online Payments';

    protected static string $view = 'filament.pages.online-payment-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->hasPermission('online_payment_settings_view') || $user?->hasPermission('online_payment_settings_edit')) ?? false;
    }

    public static function canEdit(): bool
    {
        return auth()->user()?->hasPermission('online_payment_settings_edit') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'online_payments_enabled' => (bool) Setting::get('online_payments_enabled', false),
            'online_payment_deposit_percentage' => (float) Setting::get('online_payment_deposit_percentage', 50.0),
        ]);
    }

    public function form(Form $form): Form
    {
        $readOnly = ! static::canEdit();

        return $form
            ->disabled($readOnly)
            ->schema([
                Forms\Components\Section::make('Feature Control')
                    ->description('Enable or disable online payment functionality')
                    ->icon('heroicon-o-power')
                    ->columns(1)
                    ->schema([
                        Forms\Components\Toggle::make('online_payments_enabled')
                            ->label('Enable Online Payments')
                            ->helperText('Turn off to revert to manual-only payment recording. When disabled, all payment links will return 404.')
                            ->inline(false)
                            ->default(false),
                    ]),

                Forms\Components\Section::make('Deposit Configuration')
                    ->description('Set default deposit percentage for online payments')
                    ->icon('heroicon-o-calculator')
                    ->columns(1)
                    ->schema([
                        Forms\Components\TextInput::make('online_payment_deposit_percentage')
                            ->label('Default Deposit Percentage')
                            ->helperText('Guests will pay this percentage of total charges as deposit. Balance is paid manually on check-in.')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(50.0)
                            ->required(),
                    ]),

                Forms\Components\Section::make('PayMongo Integration')
                    ->description('Payment gateway configuration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('paymongo_status')
                            ->label('API Keys Status')
                            ->content(function () {
                                $publicKey = config('paymongo.public_key');
                                $secretKey = config('paymongo.secret_key');
                                $webhookSecret = config('paymongo.webhook_secret');

                                $status = [];

                                if ($publicKey) {
                                    $keyType = str_starts_with($publicKey, 'pk_test_') ? 'TEST' : 'LIVE';
                                    $status[] = "✓ Public Key: {$keyType} mode";
                                } else {
                                    $status[] = '✗ Public Key: Not configured';
                                }

                                if ($secretKey) {
                                    $keyType = str_starts_with($secretKey, 'sk_test_') ? 'TEST' : 'LIVE';
                                    $status[] = "✓ Secret Key: {$keyType} mode";
                                } else {
                                    $status[] = '✗ Secret Key: Not configured';
                                }

                                if ($webhookSecret) {
                                    $status[] = '✓ Webhook Secret: Configured';
                                } else {
                                    $status[] = '✗ Webhook Secret: Not configured';
                                }

                                return implode("\n", $status);
                            }),

                        Forms\Components\Placeholder::make('webhook_url')
                            ->label('Webhook URL')
                            ->content(url('/api/webhooks/paymongo'))
                            ->helperText('Register this URL in your PayMongo dashboard'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! static::canEdit()) {
            Notification::make()
                ->title('Access denied.')
                ->body('You do not have permission to edit online payment configuration.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $keys = [
            'online_payments_enabled',
            'online_payment_deposit_percentage',
        ];

        $changed = false;
        foreach ($keys as $key) {
            if ($key === 'online_payments_enabled') {
                $current = (bool) Setting::get($key, false);
                $new = (bool) ($data[$key] ?? false);
            } else {
                $current = (string) Setting::get($key, '50.0');
                $new = (string) ((float) ($data[$key] ?? 50.0));
            }

            if ($current !== $new) {
                $changed = true;
                break;
            }
        }

        Setting::withoutEvents(function () use ($data) {
            Setting::set('online_payments_enabled', $data['online_payments_enabled'] ? '1' : '0');
            Setting::set('online_payment_deposit_percentage', (string) ((float) ($data['online_payment_deposit_percentage'] ?? 50.0)));
        });

        if ($changed) {
            $actor = auth()->user();
            $actorName = $actor?->name ?? 'Someone';

            $recipients = User::whereIn('role', ['admin', 'staff'])
                ->where('id', '!=', $actor?->id)
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('online_payment_settings_view'))
                ->pluck('id')
                ->toArray();

            $message = $data['online_payments_enabled']
                ? "{$actorName} enabled online payments feature."
                : "{$actorName} disabled online payments feature.";

            NotificationHelper::notifyUsers(
                $recipients,
                'Online Payment Configuration Updated',
                $message,
                'info',
                'setting',
                url('/admin/online-payment-settings')
            );

            NotificationHelper::notifyUser(
                $actor,
                'Configuration Saved',
                'You updated online payment settings.',
                'success',
                'setting',
                url('/admin/online-payment-settings')
            );
        }

        Notification::make()
            ->title('Online payment configuration saved successfully.')
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
                ->label('Save Changes')
                ->submit('save'),
        ];
    }
}
