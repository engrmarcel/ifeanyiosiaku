<?php

namespace App\Domain\Contact\Http;

use App\Domain\Contact\Actions\SubmitContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController
{
    public function store(Request $request, SubmitContactMessage $submit): JsonResponse
    {
        $submit->handle($request->only(['name', 'email', 'subject', 'message', 'page']), $request);

        return response()->json(['message' => 'Thank you — your message has been sent. You will get a reply by email.'], 201);
    }
}
