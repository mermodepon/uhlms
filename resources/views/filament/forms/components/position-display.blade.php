<div
    x-data="{
        pitch: @entangle('capturedPitch').live,
        yaw: @entangle('capturedYaw').live
    }"
    class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm"
>
    <div class="flex gap-4">
        <div>
            <span class="text-gray-500 text-xs">Pitch</span>
            <div class="font-mono font-semibold" x-text="(pitch || 0).toFixed(4) + '°'">0.0000°</div>
        </div>
        <div>
            <span class="text-gray-500 text-xs">Yaw</span>
            <div class="font-mono font-semibold" x-text="(yaw || 0).toFixed(4) + '°'">0.0000°</div>
        </div>
    </div>
    <div class="text-xs">
        <span
            class="font-medium"
            :class="pitch === 0 && yaw === 0 ? 'text-amber-500' : 'text-green-500'"
            x-text="pitch === 0 && yaw === 0 ? '⚠ Not captured' : '✓ Captured'"
        >⚠ Not captured yet</span>
    </div>
</div>

