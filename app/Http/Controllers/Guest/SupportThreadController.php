<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use App\Models\SupportInquiryReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportThreadController extends Controller
{
    private function account()
    {
        return Auth::guard('guest')->user();
    }

    public function index(): View
    {
        $account = $this->account();

        $threads = SupportInquiry::where('guest_account_id', $account->id)
            ->withCount('replies')
            ->latest()
            ->paginate(15);

        return view('guest.account.support.index', compact('account', 'threads'));
    }

    public function show(SupportInquiry $inquiry): View
    {
        $account = $this->account();
        abort_unless((int) $inquiry->guest_account_id === (int) $account->id, 403);

        $inquiry->load(['replies.sender', 'replies.guestAccount']);

        return view('guest.account.support.show', compact('account', 'inquiry'));
    }

    public function messages(SupportInquiry $inquiry)
    {
        $account = $this->account();
        abort_unless((int) $inquiry->guest_account_id === (int) $account->id, 403);

        $inquiry->load(['replies.sender', 'replies.guestAccount']);
        $lastReplyId = $inquiry->replies->last()?->id ?? 0;

        return response()->json([
            'lastReplyId' => $lastReplyId,
            'html' => view('guest.account.support.thread', compact('inquiry'))->render(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->account();

        if ($redirect = $this->redirectUnlessVerified($account)) {
            return $redirect;
        }

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportInquiry::categoryOptions()))],
            'subject'  => ['required', 'string', 'max:255'],
            'message'  => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $inquiry = SupportInquiry::create([
            'guest_account_id'  => $account->id,
            'name'              => $account->name,
            'email'             => $account->email,
            'phone'             => $account->phone,
            'category'          => $data['category'],
            'subject'           => $data['subject'],
            'message'           => $data['message'],
            'source'            => SupportInquiry::SOURCE_GUEST_ACCOUNT,
            'status'            => SupportInquiry::STATUS_NEW,
            'priority'          => SupportInquiry::PRIORITY_NORMAL,
            'email_verified_at' => $account->email_verified_at,
        ]);

        return redirect()
            ->route('guest.account.support.show', $inquiry)
            ->with('success', 'Your inquiry has been submitted. Our staff will review it and reply here.');
    }

    public function reply(Request $request, SupportInquiry $inquiry): RedirectResponse
    {
        $account = $this->account();
        abort_unless((int) $inquiry->guest_account_id === (int) $account->id, 403);

        if ($redirect = $this->redirectUnlessVerified($account)) {
            return $redirect;
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        SupportInquiryReply::create([
            'support_inquiry_id' => $inquiry->id,
            'guest_account_id'   => $account->id,
            'user_id'            => null,
            'message'            => $data['message'],
        ]);

        // Re-open the thread if resolved/archived so staff notice the new message
        if (in_array($inquiry->status, [SupportInquiry::STATUS_RESOLVED, SupportInquiry::STATUS_ARCHIVED])) {
            $inquiry->forceFill(['status' => SupportInquiry::STATUS_IN_PROGRESS])->save();
        }

        return redirect()
            ->route('guest.account.support.show', $inquiry)
            ->with('success', 'Your reply has been sent.');
    }

    private function redirectUnlessVerified($account): ?RedirectResponse
    {
        if ($account->hasVerifiedEmail()) {
            return null;
        }

        return redirect()
            ->route('guest.account.support.index')
            ->withErrors(['support' => 'Please verify your email before sending support messages.']);
    }
}
