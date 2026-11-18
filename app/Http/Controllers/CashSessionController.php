<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CashSessionController extends Controller
{
    public function index(): View
    {
        $current = Auth::user()->openCashSession();
        $sessions = CashSession::with('user')
            ->when(Auth::user()->hasRole('Cashier'), fn ($q) => $q->where('user_id', Auth::id()))
            ->latest('opened_at')->paginate(15);

        return view('register.index', compact('current', 'sessions'));
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate(['opening_float' => ['required', 'numeric', 'min:0']]);

        if (Auth::user()->openCashSession()) {
            return back()->with('error', __('You already have an open register session.'));
        }

        $session = CashSession::create([
            'user_id' => Auth::id(),
            'opening_float' => $data['opening_float'],
            'expected_cash' => $data['opening_float'],
            'status' => 'open',
            'opened_at' => now(),
        ]);

        ActivityLog::record('register', 'Opened register with float '.money($data['opening_float']), $session);

        return back()->with('success', __('Register opened. You can now start selling.'));
    }

    public function close(Request $request, CashSession $session): RedirectResponse
    {
        abort_unless($session->user_id === Auth::id() || Auth::user()->hasAnyRole(['Admin', 'Manager']), 403);

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $session->update([
            'counted_cash' => $data['counted_cash'],
            'difference' => round($data['counted_cash'] - $session->expected_cash, 2),
            'status' => 'closed',
            'closed_at' => now(),
            'note' => $data['note'] ?? null,
        ]);

        ActivityLog::record('register', 'Closed register session #'.$session->id, $session);

        return redirect()->route('register.show', $session)->with('success', __('Register closed. Z-report ready.'));
    }

    public function show(CashSession $session): View
    {
        abort_unless($session->user_id === Auth::id() || Auth::user()->hasAnyRole(['Admin', 'Manager']), 403);

        $session->load('user', 'sales');

        return view('register.show', compact('session'));
    }
}
