<div style="font-size: 14px; line-height: 1.6;">
    {{-- Summary --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div>
            <strong>Reference #:</strong> {{ $record->reference_number }}
        </div>
        <div>
            <strong>Guest:</strong> {{ $record->guest_name }}
        </div>
        <div>
            <strong>Status at Deletion:</strong> {{ ucwords(str_replace('_', ' ', $record->status)) }}
        </div>
        <div>
            <strong>Deleted By:</strong> {{ $record->deleted_by_name }}
        </div>
        <div>
            <strong>Check-in:</strong> {{ $record->check_in_date?->format('M d, Y') ?? 'N/A' }}
        </div>
        <div>
            <strong>Check-out:</strong> {{ $record->check_out_date?->format('M d, Y') ?? 'N/A' }}
        </div>
        <div style="grid-column: span 2;">
            <strong>Deleted At:</strong> {{ $record->created_at->format('M d, Y h:i A') }}
        </div>
    </div>

    {{-- Reason --}}
    <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
        <strong style="color: #dc2626;">Reason for Deletion:</strong>
        <p style="margin: 4px 0 0;">{{ $record->reason }}</p>
    </div>

    {{-- Related records deleted --}}
    @if(is_array($record->related_counts) && array_sum($record->related_counts) > 0)
        <div style="margin-bottom: 16px;">
            <strong>Related Records Deleted:</strong>
            <ul style="margin: 4px 0 0 16px;">
                @foreach($record->related_counts as $key => $count)
                    @if($count > 0)
                        <li>{{ $count }} {{ str_replace('_', ' ', $key) }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Reservation snapshot --}}
    @if(is_array($record->reservation_snapshot) && count($record->reservation_snapshot) > 0)
        <details style="margin-top: 8px;">
            <summary style="cursor: pointer; font-weight: bold; color: #6b7280;">
                View Full Reservation Snapshot
            </summary>
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-top: 8px; font-family: monospace; font-size: 12px; white-space: pre-wrap; overflow-x: auto;">{{ json_encode($record->reservation_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
        </details>
    @endif
</div>
