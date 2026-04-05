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
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
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
                'panels::topbar.start',
                fn (): string => <<<'HTML'
                <div
                    x-data="{}"
                    x-show="! $store.sidebar.isOpen"
                    x-cloak
                    class="fi-topbar-brand hidden items-center gap-2 lg:flex"
                >
                    <a href="/admin" class="flex items-center gap-2">
                        <img src="/images/uh_logo.jpg" alt="UH Lodging Management System" style="height:1.5rem; width:auto;" />
                        <span class="filament-brand-text font-semibold whitespace-nowrap leading-tight" style="color:white !important;">
                            <span style="display:block;font-size:0.5rem;line-height:1.1;">Central Mindanao University</span>
                            <span style="display:block;font-size:0.875rem;line-height:1.2;">UH Lodging Management System</span>
                        </span>
                    </a>
                </div>
                HTML,
            )
            ->renderHook(
                'panels::global-search.after',
                fn () => view('filament.topbar-date')->render(),
            )
            ->renderHook(
                'panels::head.end',
                // Render the view to a raw HTML string so Filament injects scripts/styles unescaped
                fn () => view('filament.custom-theme')->render(),
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
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
            ->renderHook(
                'panels::footer',
                fn (): string => '<div class="text-center text-xs ' . (request()->routeIs('filament.admin.auth.login') ? 'text-white' : 'text-gray-500') . ' py-4">&copy; ' . date('Y') . ' CMU University Homestay Lodging Management System. All rights reserved.</div>',
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
            ->plugin(
                FilamentFullCalendarPlugin::make()
                    ->selectable(false)
                    ->editable(false)
            )
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
