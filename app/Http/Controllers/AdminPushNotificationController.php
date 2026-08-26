<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Accountant;
use Illuminate\Http\Request;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class AdminPushNotificationController extends Controller
{
    public function create()
    {
        $users = User::all();
        $accountants = Accountant::all();

        return view('admin.notifications.push', compact('users', 'accountants'));
    }

    public function store(Request $request)
    {
        // فك JSON لو وصل كسلسلة
        if (is_string($request->target_ids)) {
            $decoded = json_decode($request->target_ids, true);
            $request->merge([
                'target_ids' => is_array($decoded) ? $decoded : []
            ]);
        }

        $request->validate([
            'target_type' => 'required|in:all,users,accountants',
            'target_ids'  => 'nullable|array',
            'title'       => 'required|string|max:255',
            'message'     => 'required|string|max:2000',
        ]);

        // تحديد الأجهزة المستهدفة
        $deviceTokens = [];

        if ($request->target_type === 'all') {
            $deviceTokens = DB::table('device_tokens')->pluck('token')->toArray();
        }

        if ($request->target_type === 'users') {
            $deviceTokens = DB::table('device_tokens')
                ->whereIn('user_id', $request->target_ids ?? [])
                ->pluck('token')
                ->toArray();
        }

        if ($request->target_type === 'accountants') {
            $deviceTokens = DB::table('device_tokens')
                ->whereIn('accountant_id', $request->target_ids ?? [])
                ->pluck('token')
                ->toArray();
        }

        // إرسال Push Notification فقط إذا فيه أجهزة
        if (!empty($deviceTokens)) {
            OneSignalService::sendToDevices($deviceTokens, $request->title, $request->message);
        }

        // إرسال Site Notification
        NotificationService::send([
            'sender_type' => 'admin',
            'target_type' => $request->target_type,
            'target_ids'  => $request->target_ids,
            'title'       => $request->title,
            'message'     => $request->message,
        ]);

        return back()->with('success', 'تم إرسال الإشعار بنجاح');
    }
}
