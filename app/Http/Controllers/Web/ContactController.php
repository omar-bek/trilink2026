<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'in:general,sales,support,partnership,compliance'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Feedback::create([
            'type' => 'contact_'.$data['subject'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'],
            'meta' => ['ip' => $request->ip(), 'ua' => $request->userAgent()],
        ]);

        return back()->with('status', __('contact.sent'));
    }
}
