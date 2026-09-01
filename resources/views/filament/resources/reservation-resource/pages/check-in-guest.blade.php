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

            @if($occupiedHeldRooms !== [])
                <x-filament::button
                    type="button"
                    color="warning"
                    outlined
                    wire:click="refreshRoomAvailability"
                    wire:loading.attr="disabled"
                    wire:target="refreshRoomAvailability"
                >
                    Refresh Availability
                </x-filament::button>
            @endif

            <x-filament::button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                :disabled="$occupiedHeldRooms !== []"
            >
                <span wire:loading.remove wire:target="submit">{{ $occupiedHeldRooms !== [] ? 'Resolve Room Availability First' : 'Complete Check-In' }}</span>
                <span wire:loading wire:target="submit">Processing...</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
