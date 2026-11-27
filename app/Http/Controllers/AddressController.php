<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Requests\AddressRequest;
use App\Models\UserAddress;
use App\Services\AddressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sổ địa chỉ của khách. Đơn hàng đã lưu snapshot nên sửa / xóa địa chỉ không ảnh hưởng đơn cũ. */
class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.addresses', ['addresses' => $request->user()->addresses()->get()]);
    }

    public function store(AddressRequest $request, AddressService $addresses): RedirectResponse
    {
        try {
            $addresses->create($request->user(), $request->safe()->except('is_default'), $request->boolean('is_default'));
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã thêm địa chỉ.');
    }

    public function update(AddressRequest $request, UserAddress $address, AddressService $addresses): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $addresses->update($address, $request->safe()->except('is_default'));

        return back()->with('success', 'Đã cập nhật địa chỉ.');
    }

    public function makeDefault(Request $request, UserAddress $address, AddressService $addresses): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $addresses->makeDefault($address);

        return back()->with('success', 'Đã đặt làm địa chỉ mặc định.');
    }

    public function destroy(Request $request, UserAddress $address, AddressService $addresses): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $addresses->delete($address);

        return back()->with('success', 'Đã xóa địa chỉ.');
    }
}
