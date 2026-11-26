<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\ContactChannel;
use App\Services\ContactChannelService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin → Kênh liên hệ: link Zalo / Facebook / TikTok hiện ở bong bóng góc phải trang khách. */
class ContactChannelController extends Controller
{
    public function index(): View
    {
        return view('admin.contact-channels.index', ['saved' => ContactChannel::all()->keyBy('channel')]);
    }

    public function update(Request $request, ContactChannelService $channels): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(ContactChannel::CHANNELS) as $c) {
            $rules["channels.{$c}.url"] = ['nullable', 'string', 'max:255'];
            $rules["channels.{$c}.sort_order"] = ['nullable', 'integer', 'min:0', 'max:99'];
        }
        $request->validate($rules, [], ['channels.*.url' => 'link', 'channels.*.sort_order' => 'thứ tự']);

        $rows = [];
        foreach (array_keys(ContactChannel::CHANNELS) as $i => $c) {
            $rows[$c] = [
                'url' => $request->input("channels.{$c}.url"),
                'is_active' => $request->boolean("channels.{$c}.is_active"),
                'sort_order' => (int) $request->input("channels.{$c}.sort_order", $i),
            ];
        }
        try {
            $channels->save($rows);
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.contact-channels.index')->with('success', 'Đã lưu kênh liên hệ.');
    }
}
