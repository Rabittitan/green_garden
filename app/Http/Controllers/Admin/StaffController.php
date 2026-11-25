<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Trang "Nhân viên" (chỉ quản trị viên): danh sách, tạo tài khoản, cấp nhóm quyền, khóa / mở, đặt lại mật khẩu. */
class StaffController extends Controller
{
    public function __construct(private StaffService $staff) {}

    public function index(): View
    {
        $people = User::whereIn('role', [UserRole::Staff->value, UserRole::Admin->value])
            ->orderByRaw("role = 'admin' DESC")->orderBy('name')->get();

        return view('admin.staff.index', compact('people'));
    }

    public function create(): View
    {
        return view('admin.staff.create', ['member' => new User(['permissions' => StaffPermission::values()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(StaffPermission::values())],
        ], [], ['name' => 'họ tên', 'permissions' => 'quyền']);
        $member = $this->staff->create($data['name'], $data['email'], $data['password'], $data['permissions'] ?? []);

        return redirect()->route('admin.staff.index')->with('success', "Đã tạo tài khoản nhân viên {$member->email}.");
    }

    public function edit(User $staff): View|RedirectResponse
    {
        if ($staff->role !== UserRole::Staff) {
            return redirect()->route('admin.staff.index')->with('error', 'Chỉ sửa được tài khoản nhân viên ở đây.');
        }

        return view('admin.staff.edit', ['member' => $staff]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
            'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(StaffPermission::values())],
        ], [], ['name' => 'họ tên', 'permissions' => 'quyền']);
        try {
            $this->staff->update($staff, $data['name'], $data['permissions'] ?? [], $request->boolean('is_active'), $data['password'] ?? null);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.staff.index')->with('success', "Đã cập nhật nhân viên {$staff->email}.");
    }
}
