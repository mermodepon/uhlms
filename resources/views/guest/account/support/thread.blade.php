<div id="support-thread" class="space-y-4 mb-8" data-last-reply-id="{{ $inquiry->replies->last()?->id ?? 0 }}">
    <div class="flex justify-end">
        <div class="max-w-[80%]">
            <div class="rounded-2xl rounded-tr-sm bg-[#00491E] text-white px-4 py-3 shadow-sm">
                <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $inquiry->message }}</p>
            </div>
            <div class="mt-1 text-right text-xs text-gray-400">
                You &bull; {{ $inquiry->created_at->format('M d, Y g:i A') }}
            </div>
        </div>
    </div>

    @foreach($inquiry->replies as $reply)
        @if($reply->isFromStaff())
            <div class="flex justify-start">
                <div class="max-w-[80%]">
                    <div class="rounded-2xl rounded-tl-sm bg-white border border-gray-200 px-4 py-3 shadow-sm">
                        <p class="text-sm leading-relaxed whitespace-pre-wrap text-gray-800">{{ $reply->message }}</p>
                    </div>
                    <div class="mt-1 text-left text-xs text-gray-400">
                        <span class="font-medium text-[#00491E]">University Homestay Staff</span>
                        &bull; {{ $reply->created_at->format('M d, Y g:i A') }}
                    </div>
                </div>
            </div>
        @else
            <div class="flex justify-end">
                <div class="max-w-[80%]">
                    <div class="rounded-2xl rounded-tr-sm bg-[#00491E] text-white px-4 py-3 shadow-sm">
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $reply->message }}</p>
                    </div>
                    <div class="mt-1 text-right text-xs text-gray-400">
                        You &bull; {{ $reply->created_at->format('M d, Y g:i A') }}
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    @if($inquiry->replies->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 py-8 text-center text-sm text-gray-400">
            Our staff will reply here shortly.
        </div>
    @endif
</div>
