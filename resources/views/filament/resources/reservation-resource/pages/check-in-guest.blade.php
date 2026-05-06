<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-x-3">
            <x-filament::button
                tag="a"
                :href="$this->getResource()::getUrl('index')"
                color="gray"
                outlined
            >
                Cancel
            </x-filament::button>

            <x-filament::button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">Complete Check-In</span>
                <span wire:loading wire:target="submit">Processing...</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
