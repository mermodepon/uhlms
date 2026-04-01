<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('UH Lodging Management System')
            ->favicon(asset('images/uh_logo.jpg'))
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->darkModeBrandLogo(fn () => view('filament.brand-logo'))
            ->renderHook(
                'panels::head.end',
                // Render the view to a raw HTML string so Filament injects scripts/styles unescaped
                fn () => view('filament.custom-theme')->render(),
            )
            ->renderHook(
                'panels::topbar.end',
                fn () => view('filament.notification-center')->render(),
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => <<<'HTML'
                <script>
                    document.addEventListener('livewire:init', () => {
                        Livewire.hook('request', ({ fail }) => {
                            fail(({ status, preventDefault }) => {
                                if (status === 419) {
                                    preventDefault();
                                    window.location.href = '/admin/login';
                                }
                            });
                        });
                    });
                </script>
                HTML,
            )
            ->colors([
                'primary' => Color::hex('#00491E'),
                'danger' => Color::Red,
                'gray' => Color::Zinc,
                'info' => Color::hex('#02681E'),
                'success' => Color::hex('#919F02'),
                'warning' => Color::hex('#FFC600'),
            ])
            ->navigationGroups([
                NavigationGroup::make('Reservation Management')
                    ->icon('heroicon-o-calendar-days'),
                NavigationGroup::make('Room Management')
                    ->icon('heroicon-o-home-modern'),
                NavigationGroup::make('Reports')
                    ->icon('heroicon-o-chart-bar'),
                NavigationGroup::make('Administration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
