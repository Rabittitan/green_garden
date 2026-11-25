<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GoodsReceiptController as AdminGoodsReceiptController;
use App\Http\Controllers\Admin\HomeSlideController as AdminHomeSlideController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PlantController as AdminPlantController;
use App\Http\Controllers\Admin\PurchaseRequestController as AdminPurchaseRequestController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\SupplierController as AdminSupplierController;
use App\Http\Controllers\Admin\TierController as AdminTierController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// ===== KHÁCH VÃNG LAI =====
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/cay-canh', [PlantController::class, 'index'])->name('plants.index');
Route::get('/cay-canh/{id}', [PlantController::class, 'show'])->name('plants.show');
Route::get('/tin-tuc', [ArticleController::class, 'index'])->name('news.index');
Route::get('/tin-tuc/{id}', [ArticleController::class, 'show'])->name('news.show');

// ===== GIỎ HÀNG (session, không cần đăng nhập) =====
Route::get('/gio-hang', [CartController::class, 'index'])->name('cart.index');
Route::post('/gio-hang/them/{id}', [CartController::class, 'add'])->whereNumber('id')->name('cart.add');
Route::post('/gio-hang/cap-nhat/{id}', [CartController::class, 'update'])->whereNumber('id')->name('cart.update');
Route::post('/gio-hang/xoa/{id}', [CartController::class, 'remove'])->whereNumber('id')->name('cart.remove');

// ===== CỔNG THANH TOÁN =====
// IPN: VNPay gọi server -> server. Không đăng nhập, không CSRF (GET). Nơi DUY NHẤT ghi nhận tiền.
Route::get('/thanh-toan/vnpay/ipn', [PaymentController::class, 'vnpayIpn'])->name('payment.vnpay.ipn');
// Cổng giả lập cho dev (PAYMENT_GATEWAY=fake), tự trả 404 khi dùng VNPay thật
Route::get('/thanh-toan/gia-lap', [PaymentController::class, 'fakeShow'])->name('payment.fake.show');
Route::post('/thanh-toan/gia-lap', [PaymentController::class, 'fakeSubmit'])->name('payment.fake.submit');

// ===== ĐĂNG NHẬP / ĐĂNG KÝ =====
Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:login');
    Route::get('/dang-ky', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->name('register.post')->middleware('throttle:5,1');
    // Quên mật khẩu (tên route theo chuẩn Laravel: mail đặt lại dùng route('password.reset'))
    Route::get('/quen-mat-khau', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/quen-mat-khau', [PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/dat-lai-mat-khau/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/dat-lai-mat-khau', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:5,1');
});
Route::post('/dang-xuat', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ===== XÁC THỰC EMAIL =====
Route::middleware('auth')->group(function () {
    Route::get('/email/xac-thuc', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/xac-thuc/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/gui-lai', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');
});

// ===== TÀI KHOẢN + ĐƠN HÀNG CỦA TÔI (đăng nhập; chưa xác thực email vẫn xem được) =====
Route::middleware('auth')->group(function () {
    Route::get('/tai-khoan', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/tai-khoan', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/tai-khoan/mat-khau', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/tai-khoan/hang-thanh-vien', [MembershipController::class, 'show'])->name('account.membership');

    Route::get('/tai-khoan/dia-chi', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/tai-khoan/dia-chi', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/tai-khoan/dia-chi/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::post('/tai-khoan/dia-chi/{address}/mac-dinh', [AddressController::class, 'makeDefault'])->name('addresses.default');
    Route::delete('/tai-khoan/dia-chi/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    Route::get('/don-hang', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/don-hang/{order:code}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/don-hang/{order:code}/thanh-toan', [OrderController::class, 'pay'])->name('orders.pay')->middleware('throttle:20,1');
    Route::post('/don-hang/{order:code}/huy', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/don-hang/{order:code}/tra-hang', [OrderController::class, 'requestReturn'])->name('orders.return');
    Route::post('/don-hang/{order:code}/ghi-chu', [OrderController::class, 'updateNote'])->name('orders.note');
    Route::get('/don-hang/{order:code}/hoa-don', [OrderController::class, 'invoice'])->name('orders.invoice');

    // Trình duyệt quay về từ VNPay: chỉ hiển thị
    Route::get('/thanh-toan/vnpay/ket-qua', [PaymentController::class, 'vnpayReturn'])->name('payment.vnpay.return');
});

// ===== ĐÁNH GIÁ + HỎI ĐÁP (đăng nhập; chống spam bằng throttle) =====
Route::middleware(['auth', 'throttle:20,1'])->group(function () {
    Route::post('/cay-canh/{id}/danh-gia', [ReviewController::class, 'store'])->whereNumber('id')->name('reviews.store');
    Route::delete('/danh-gia/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::post('/danh-gia/{review}/huu-ich', [ReviewController::class, 'vote'])->name('reviews.vote');
    Route::post('/cay-canh/{id}/hoi-dap', [CommentController::class, 'storeForPlant'])->whereNumber('id')->name('comments.plant');
    Route::post('/tin-tuc/{id}/binh-luan', [CommentController::class, 'storeForArticle'])->whereNumber('id')->name('comments.article');
    Route::delete('/binh-luan/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
});

// ===== ĐẶT HÀNG: đăng nhập + đã xác thực email =====
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dat-hang', [CheckoutController::class, 'form'])->name('checkout.form');
    Route::post('/dat-hang/ap-ma', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon.apply')->middleware('throttle:20,1');
    Route::post('/dat-hang/bo-ma', [CheckoutController::class, 'removeCoupon'])->name('checkout.coupon.remove');
    Route::post('/dat-hang', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:10,1');
});

// ===== QUẢN TRỊ =====
// 'admin'          : nhân viên + quản trị
// 'admin:<quyền>'  : quản trị, hoặc nhân viên được cấp nhóm quyền đó (App\Enums\StaffPermission; trang Nhân viên)
// 'admin:admin'    : chỉ quản trị (mã giảm giá, hạng, khách hàng, nhân viên, tiền: xác nhận chuyển khoản / hoàn tiền, duyệt nhập)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    // Trang đầu: nhân viên không có quyền "Thống kê" được chuyển tới mục đầu tiên mình được dùng
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('admin:catalog')->group(function () {
        Route::resource('plants', AdminPlantController::class)->except(['show']);
    });

    Route::middleware('admin:orders')->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('orders.invoice');
        Route::post('/orders/{order}/confirm', [AdminOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('/orders/{order}/ship', [AdminOrderController::class, 'ship'])->name('orders.ship');
        Route::post('/orders/{order}/complete', [AdminOrderController::class, 'complete'])->name('orders.complete');
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/returning', [AdminOrderController::class, 'returning'])->name('orders.returning');
        Route::post('/orders/{order}/receive-return', [AdminOrderController::class, 'receiveReturn'])->name('orders.receive-return');
        Route::post('/orders/{order}/cancel-return', [AdminOrderController::class, 'cancelReturn'])->name('orders.cancel-return');
    });

    Route::middleware('admin:inventory')->group(function () {
        Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{plant}', [AdminInventoryController::class, 'show'])->name('inventory.show');
        Route::post('/inventory/{plant}/damage', [AdminInventoryController::class, 'damage'])->name('inventory.damage');
    });

    // Đánh giá + hỏi đáp
    Route::middleware('admin:reviews')->group(function () {
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review}/moderate', [AdminReviewController::class, 'moderate'])->name('reviews.moderate');
        Route::post('/reviews/{review}/reply', [AdminReviewController::class, 'reply'])->name('reviews.reply');
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::get('/comments', [AdminCommentController::class, 'index'])->name('comments.index');
        Route::post('/comments/{comment}/reply', [AdminCommentController::class, 'reply'])->name('comments.reply');
        Route::post('/comments/{comment}/toggle', [AdminCommentController::class, 'toggle'])->name('comments.toggle');
    });

    // Tin tức (ảnh bìa / ảnh trong bài) + slide ảnh đầu trang chủ
    Route::middleware('admin:content')->group(function () {
        Route::post('/articles/images', [AdminArticleController::class, 'uploadImage'])->name('articles.images');
        Route::resource('articles', AdminArticleController::class);
        Route::resource('slides', AdminHomeSlideController::class)->except(['show']);
    });

    // Nhập hàng: nhân viên lập yêu cầu / phiếu nhập, nhập kho (duyệt yêu cầu: nhóm admin:admin bên dưới)
    Route::middleware('admin:purchasing')->group(function () {
        Route::resource('suppliers', AdminSupplierController::class)->only(['index', 'store', 'edit', 'update']);
        Route::post('/purchase-requests/from-low-stock', [AdminPurchaseRequestController::class, 'fromLowStock'])->name('purchase-requests.from-low-stock');
        Route::resource('purchase-requests', AdminPurchaseRequestController::class)->except(['destroy']);
        Route::post('/purchase-requests/{purchase_request}/transition', [AdminPurchaseRequestController::class, 'transition'])->name('purchase-requests.transition');
        Route::post('/purchase-requests/{purchase_request}/order', [AdminPurchaseRequestController::class, 'order'])->name('purchase-requests.order');
        Route::resource('goods-receipts', AdminGoodsReceiptController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/goods-receipts/{goods_receipt}/complete', [AdminGoodsReceiptController::class, 'complete'])->name('goods-receipts.complete');
        Route::post('/goods-receipts/{goods_receipt}/cancel', [AdminGoodsReceiptController::class, 'cancel'])->name('goods-receipts.cancel');
    });

    Route::middleware('admin:admin')->group(function () {
        // Kiểm kê (điều chỉnh tồn theo số đếm) là việc của quản trị viên; nhân viên chỉ ghi hư hỏng / cây chết
        Route::post('/inventory/{plant}/count', [AdminInventoryController::class, 'count'])->name('inventory.count');
        Route::post('/purchase-requests/{purchase_request}/approve', [AdminPurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
        Route::post('/purchase-requests/{purchase_request}/reject', [AdminPurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
        Route::post('/orders/{order}/confirm-transfer', [AdminOrderController::class, 'confirmTransfer'])->name('orders.confirm-transfer');
        Route::post('/orders/{order}/refunds', [AdminOrderController::class, 'requestRefund'])->name('orders.refunds.store');
        Route::post('/payments/{payment}/confirm-refund', [AdminOrderController::class, 'confirmRefund'])->name('payments.confirm-refund');

        Route::resource('coupons', AdminCouponController::class)->except(['show']);
        Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{user}', [AdminCustomerController::class, 'show'])->name('customers.show');
        Route::post('/customers/{user}/tier', [AdminCustomerController::class, 'setTier'])->name('customers.tier');
        Route::post('/customers/{user}/toggle-active', [AdminCustomerController::class, 'toggleActive'])->name('customers.toggle');
        Route::get('/tiers', [AdminTierController::class, 'index'])->name('tiers.index');
        Route::put('/tiers', [AdminTierController::class, 'update'])->name('tiers.update');

        // Nhân viên: tạo tài khoản, cấp nhóm quyền, khóa / mở, đặt lại mật khẩu
        Route::resource('staff', AdminStaffController::class)->except(['show', 'destroy'])->parameters(['staff' => 'staff']);
    });
});
