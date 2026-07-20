<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function updateAdminFields(Request $request)
    {
        $user = auth()->user();

        $user->admin_bot = $request->input('admin_bot');
        $user->admin_channel = $request->input('admin_channel');

        // Если чекбокс стоит, будет true, иначе false
        $user->notifications = $request->has('notifications');
        $user->save();

        return redirect()->back();
    }
}
