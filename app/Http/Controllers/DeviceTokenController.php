<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = auth()->user();

        // تحديد نوع المستخدم
        $data = ['token' => $request->token];

        if ($user->role === 'user') {
            $data['user_id'] = $user->id;
        }

        if ($user->role === 'accountant') {
            $data['accountant_id'] = $user->id;
        }

        // منع التكرار
        DeviceToken::updateOrCreate(
            ['token' => $request->token],
            $data
        );

        return response()->json(['status' => 'saved']);
    }
}
