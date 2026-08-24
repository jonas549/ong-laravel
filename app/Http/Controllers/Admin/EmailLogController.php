<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        $correos = EmailLog::query()
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), function ($q, $b) {
                $q->where(function ($w) use ($b) {
                    $w->where('to', 'like', "%{$b}%")->orWhere('subject', 'like', "%{$b}%");
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.emails.index', [
            'correos' => $correos,
            'enviados' => EmailLog::enviados()->count(),
            'fallidos' => EmailLog::fallidos()->count(),
        ]);
    }

    public function show(EmailLog $email)
    {
        return view('admin.emails.show', compact('email'));
    }
}
