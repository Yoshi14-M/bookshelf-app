<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * 自分の通知の一覧（新しい順・全件。既読の通知も残す）。
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にする。
     *
     * 自分の通知の中から探すため、他人の通知や存在しない ID は存在を隠して 404 になる。
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return redirect()->route('notifications.index')->with('success', '通知を既読にしました。');
    }
}
