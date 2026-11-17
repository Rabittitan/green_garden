<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressRequest;
use App\Models\UserAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Sổ địa chỉ của khách. Đơn hàng đã lưu snapshot nên sửa / xóa địa chỉ không ảnh hưởng đơn cũ. */
class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.addresses', ['addresses' => $request->user()->addresses()->get()]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($request, $user) {
            $makeDefault = $request->boolean('is_default') || ! $user->addresses()->exists();
            if ($makeDefault) {
                UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
            }
            $user->addresses()->create($request->safe()->except('is_default') + ['is_default' => $makeDefault]);
        });

        return back()->with('success', 'Đã thêm địa chỉ.');
    }

    public function update(AddressRequest $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->update($request->safe()->except('is_default'));

        return back()->with('success', 'Đã cập nhật địa chỉ.');
    }

    public function makeDefault(Request $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($address) {
            UserAddress::where('user_id', $address->user_id)->update(['is_default' => false]);   // bỏ mặc định cũ TRƯỚC (UNIQUE)
            $address->update(['is_default' => true]);
        });

        return back()->with('success', 'Đã đặt làm địa chỉ mặc định.');
    }

    public function destroy(Request $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                UserAddress::where('user_id', $address->user_id)->latest('id')->first()?->update(['is_default' => true]);
            }
        });

        return back()->with('success', 'Đã xóa địa chỉ.');
    }
}
