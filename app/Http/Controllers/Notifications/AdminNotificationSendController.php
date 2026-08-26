<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Accountant;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminNotificationSendController extends Controller
{
    public function create()
    {
        $users = User::all();
        $accountants = Accountant::all();

        return view('admin.notifications.send', compact('users', 'accountants'));
    }

    public function store(Request $request)
    {
        // فك JSON لو وصل كسلسلة
        if ($request->filled('target_ids') && is_string($request->target_ids)) {
            $decoded = json_decode($request->target_ids, true);
            $request->merge([
                'target_ids' => is_array($decoded) ? $decoded : null,
            ]);
        }

        $request->validate([
            'target_type' => 'required|in:all,users,accountants',
            'target_ids'  => 'nullable|array',
            'title'       => 'required|string|max:255',
            'message'     => 'required|string|max:2000',
        ]);

        NotificationService::send([
            'sender_type' => 'admin',
            'target_type' => $request->target_type,
            'target_ids'  => $request->target_ids,
            'title'       => $request->title,
            'message'     => $request->message,
        ]);

        return redirect()
            ->route('notifications.internal.send')
            ->with('success', 'تم إرسال الإشعار بنجاح');
    }
}
