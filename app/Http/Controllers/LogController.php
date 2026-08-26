<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = auth()->id();

        $query = Log::query()
            ->with(['user', 'store', 'actor', 'model'])
            ->where('user_id', $ownerId)
            ->latest();

        $selectedAction = trim((string) $request->input('action', ''));
        if ($selectedAction !== '') {
            if (str_starts_with($selectedAction, 'g:')) {
                $generalAction = substr($selectedAction, 2);
                $query->whereIn('action', EventPolicy::expandToSpecific($generalAction));
            } elseif (str_starts_with($selectedAction, 's:')) {
                $specificAction = substr($selectedAction, 2);
                $query->where('action', $specificAction);
            } elseif (EventPolicy::isGeneralAction($selectedAction)) {
                $query->whereIn('action', EventPolicy::expandToSpecific($selectedAction));
            } else {
                $query->where('action', $selectedAction);
        $logs = $query->paginate(30)->withQueryString();
        $specificActions = Log::query()
            ->where('user_id', $ownerId)
            ->select('action')
            ->pluck('action')
            ->map(fn ($action) => (string) $action)
            ->filter(fn ($action) => $action !== '')
            ->sort()
            ->values();

        $generalActions = $specificActions
            ->map(fn ($action) => EventPolicy::generalFor($action))
            ->filter(fn ($action) => $action !== '')
            ->unique()
            ->sort()
            ->values();
        return view('user.logs.index', compact('logs', 'generalActions', 'specificActions', 'selectedAction'));
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $query->where('description', 'LIKE', "%{$request->search}%");
        }

        $logs = $query->paginate(30);

        $actions = Log::query()
            ->when(auth()->check(), fn ($q) => $q->where('user_id', auth()->id()))
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('user.logs.index', compact('logs', 'actions'));
    }
}
}
