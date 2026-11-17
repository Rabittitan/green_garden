<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PlantController as AdminPlantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

// ===== KHÁCH VÃNG LAI (ai cũng xem được) =====
Route::get('/', [HomeController::class, 'index'])->name('home');                 // Trang chủ
Route::get('/cay-canh', [PlantController::class, 'index'])->name('plants.index'); // Danh sách + bộ lọc
Route::get('/cay-canh/{id}', [PlantController::class, 'show'])->name('plants.show'); // Chi tiết cây

// ===== GIỎ HÀNG (lưu bằng session, không cần đăng nhập) =====
Route::get('/gio-hang', [CartController::class, 'index'])->name('cart.index');
Route::post('/gio-hang/them/{id}', [CartController::class, 'add'])->name('cart.add');
Route::post('/gio-hang/cap-nhat/{id}', [CartController::class, 'update'])->name('cart.update');
Route::post('/gio-hang/xoa/{id}', [CartController::class, 'remove'])->name('cart.remove');

// ===== ĐĂNG NHẬP / ĐĂNG KÝ =====
// throttle:5,1 = tối đa 5 lần / 1 phút / IP, chống dò mật khẩu
Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
    Route::get('/dang-ky', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->name('register.post')->middleware('throttle:5,1');
});
Route::post('/dang-xuat', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ===== XÁC THỰC EMAIL (tên route phải đúng verification.* vì middleware 'verified' dùng tên này) =====
Route::middleware('auth')->group(function () {
    Route::get('/email/xac-thuc', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/xac-thuc/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');     // signed: link có chữ ký, sửa tay là hỏng
    Route::post('/email/gui-lai', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');                 // tối đa 3 lần gửi lại / phút
});

// ===== HỒ SƠ + LỊCH SỬ ĐƠN (chỉ cần đăng nhập; chưa xác thực vẫn vào được để sửa email gõ sai) =====
Route::middleware('auth')->group(function () {
    Route::get('/tai-khoan', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/tai-khoan', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/tai-khoan/mat-khau', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/don-hang', [CheckoutController::class, 'history'])->name('orders.history');
    Route::get('/don-hang/{id}', [CheckoutController::class, 'show'])->name('orders.show');
});

// ===== ĐẶT HÀNG: phải đăng nhập + ĐÃ XÁC THỰC EMAIL (chưa xác thực sẽ bị chuyển tới verification.notice) =====
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dat-hang', [CheckoutController::class, 'form'])->name('checkout.form');
    Route::post('/dat-hang', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:10,1');
});

// newsss 
Route::get('/tin-tuc', [ArticleController::class, 'index'])->name('news.index');     // Danh sách tin
Route::get('/tin-tuc/{id}', [ArticleController::class, 'show'])->name('news.show');  // Chi tiết 1 tin

// ===== KHU VỰC ADMIN (phải đăng nhập + là admin) =====
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');            // Thống kê
    Route::resource('plants', AdminPlantController::class)->except(['show']);              // CRUD cây (7 route tự sinh)
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');   // Danh sách đơn
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show'); // Chi tiết đơn
    Route::post('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status'); // Đổi trạng thái / hủy đơn
});