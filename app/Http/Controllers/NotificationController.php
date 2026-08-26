<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * تحديد المستخدم الحالي حسب نوع الحساب
     */
    private function currentUser()
    {
        if (Auth::guard('accountant')->check()) {
            return Auth::guard('accountant')->user();
        }

        if (Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }

        return null;
    }

    /**
     * عرض جميع الإشعارات
     */
    public function index()
    {
        $user = $this->currentUser();

        $notifications = Notification::where(function ($query) use ($user) {
            $query->where('target_type', 'all')
                ->orWhereJsonContains('target_ids', $user->id);
        })
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * عرض إشعار واحد
     */
    public function show($id)
    {
        $user = $this->currentUser();
        $notification = Notification::findOrFail($id);

        if (
            $notification->target_type !== 'all' &&
            !in_array($user->id, $notification->target_ids ?? [])
        ) {
            abort(403, 'غير مصرح لك بعرض هذا الإشعار');
        }

        return view('notifications.show', compact('notification'));
    }

    /**
     * تبديل حالة الإشعار (مقروء ↔ غير مقروء)
     */
    public function toggle($id)
    {
        $user = $this->currentUser();
        $n = Notification::findOrFail($id);

        $read = $n->read_by ?? [];

        if (in_array($user->id, $read)) {
            $read = array_diff($read, [$user->id]);
        } else {
            $read[] = $user->id;
        }

        $n->update(['read_by' => array_values($read)]);

        return back();
    }

    /**
     * تعليم كل الإشعارات كمقروءة
     */
    public function markAll()
    {
        $user = $this->currentUser();

        $notifications = Notification::where(function ($q) use ($user) {
            $q->where('target_type', 'all')
              ->orWhereJsonContains('target_ids', $user->id);
        })->get();

        foreach ($notifications as $n) {
            $n->markAsRead($user->id);
        }

        return back();
    }

    /**
     * تعليم الإشعارات المحددة كمقروءة
     */
    public function markSelected(Request $request)
    {
        
        $user = $this->currentUser();
        $ids = $request->selected ?? [];

        if (empty($ids)) {
            return back()->with('error', 'لم يتم تحديد أي إشعار');
        }

        $notifications = Notification::whereIn('id', $ids)->get();

        foreach ($notifications as $n) {
            $n->markAsRead($user->id);
        }

        return back()->with('success', 'تم تعليم الإشعارات المحددة كمقروءة');
    }

    /**
     * حذف إشعار واحد
     */
    public function delete(Request $request, $id)
    {
        $user = $this->currentUser();
        $n = Notification::findOrFail($id);

        $targets = $n->target_ids ?? [];

        if (in_array($user->id, $targets)) {
            $targets = array_diff($targets, [$user->id]);
            $n->update(['target_ids' => array_values($targets)]);
        }

        if (empty($targets) && $n->target_type !== 'all') {
            $n->delete();
        }

       return redirect($request->redirect_to);
    }
  public function remov(Request $request, $id)
{
    $user = $this->currentUser();
    $n = Notification::findOrFail($id);

    // إذا الإشعار عام (all) → لا نحذفه من قاعدة البيانات
    if ($n->target_type === 'all') {

        $hidden = $n->read_by ?? [];

        // نضيف علامة إخفاء خاصة بهذا المستخدم
        $hidden[] = "hidden_by_{$user->id}";

        $n->update(['read_by' => $hidden]);

        return back();
    }

    // إذا الإشعار لمستخدمين محددين
    $targets = $n->target_ids ?? [];

    // احذف المستخدم من القائمة
    $targets = array_values(array_diff($targets, [$user->id]));

    // إذا لم يبقَ أحد → احذف الإشعار
    if (empty($targets)) {
        $n->delete();
    } else {
        $n->update(['target_ids' => $targets]);
    }

    return back();
}



}
