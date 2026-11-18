<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%'.$request->q.'%'))
            ->latest()->paginate(30)->withQueryString();

        $actions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('activity.index', compact('logs', 'actions'));
    }
}
