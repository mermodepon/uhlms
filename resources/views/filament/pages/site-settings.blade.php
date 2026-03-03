<x-filament-panels::page>
    @unless(\App\Filament\Pages\SiteSettings::canEdit())
        <x-filament::section>
            <div class="flex items-center gap-3 text-warning-600 dark:text-warning-400">
                <x-heroicon-o-eye class="w-5 h-5 shrink-0" />
                <span class="text-sm font-medium">You have read-only access to site settings. Contact a super admin to request edit permissions.</span>
            </div>
        </x-filament::section>
    @endunless

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
