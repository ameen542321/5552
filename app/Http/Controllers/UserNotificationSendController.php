<?php

namespace App\Http\Controllers;

use App\Models\Accountant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class UserNotificationSendController extends Controller
{
    public function create()
    {
        $user = auth()->user();

        // المحاسبون التابعون للمستخدم
        $accountants = Accountant::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('notifications.send', compact('accountants'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'target_type' => 'required|in:accountant,accountants,admin',
            'target_ids'  => 'nullable|array',
            'title'       => 'required|string|max:255',
            'message'     => 'required|string|max:2000',
        ]);

        $user = auth()->user();
        $targetType = $request->input('target_type');
        $targetIds = collect($request->input('target_ids', []))
            ->filter(fn ($id) => !is_null($id) && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (in_array($targetType, ['accountant', 'accountants'], true)) {
            $targetIds = Accountant::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereIn('id', $targetIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (empty($targetIds)) {
                return back()->withErrors(['target_ids' => 'اختر محاسبًا واحدًا على الأقل من المحاسبين التابعين لك.'])->withInput();
            }

            if ($targetType === 'accountant') {
                $targetIds = array_slice($targetIds, 0, 1);
            }

            $targetType = 'accountants';
        }

        if ($targetType === 'admin') {
            $targetIds = User::where('role', 'admin')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $targetType = 'users';
        }

        NotificationService::send([
            'sender_id'   => $user->id,
            'sender_type' => 'user',
            'target_type' => $targetType,
            'target_ids'  => $targetIds,
            'title'       => $request->title,
            'message'     => $request->message,
        ]);

        return redirect()
            ->route('user.notifications.send')
            ->with('success', 'تم إرسال الإشعار بنجاح');
    }
}
