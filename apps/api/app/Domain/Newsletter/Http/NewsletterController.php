<?php

namespace App\Domain\Newsletter\Http;

use App\Domain\Newsletter\Newsletter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController
{
    public function __construct(private readonly Newsletter $newsletter) {}

    public function subscribe(Request $request): JsonResponse
    {
        // Sign-up is switched off until an email provider can deliver confirmation emails.
        abort_unless($this->newsletter->enabled(), 404);

        $this->newsletter->subscribe($request->only(['email', 'consent', 'source']), $request);

        return response()->json(['message' => 'Almost done — please check your inbox and click the link to confirm.'], 202);
    }

    /** Works even while sign-up is switched off, so pending people can still confirm. */
    public function confirm(Request $request): JsonResponse
    {
        $ok = $this->newsletter->confirm((string) $request->input('s'), (string) $request->input('t'));

        return $ok
            ? response()->json(['message' => 'You are subscribed. Thank you!'])
            : response()->json(['error' => ['code' => 'link_invalid', 'message' => 'This confirmation link is invalid or has expired. Please sign up again.']], 410);
    }

    /** Always available: people must be able to leave at any time. */
    public function unsubscribe(Request $request): JsonResponse
    {
        $ok = $this->newsletter->unsubscribe((string) $request->input('s'), (string) $request->input('t'));

        return $ok
            ? response()->json(['message' => 'You have been unsubscribed and will not receive further newsletters.'])
            : response()->json(['error' => ['code' => 'link_invalid', 'message' => 'This unsubscribe link is not valid. Please use the link from your most recent email.']], 410);
    }
}
