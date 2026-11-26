{{-- Điều hướng khu tài khoản (dùng chung cho hồ sơ / đơn hàng / hạng / sổ địa chỉ) --}}
<nav class="gg-account-nav" aria-label="Tài khoản">
    <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}"><i class="fa fa-id-card me-1"></i> Hồ sơ của tôi</a>
    <a href="{{ route('orders.index') }}" class="{{ request()->routeIs('orders.*') ? 'active' : '' }}"><i class="fa fa-receipt me-1"></i> Đơn hàng của tôi</a>
    <a href="{{ route('account.coupons') }}" class="{{ request()->routeIs('account.coupons') ? 'active' : '' }}"><i class="fa fa-ticket me-1"></i> Mã của tôi</a>
    <a href="{{ route('account.membership') }}" class="{{ request()->routeIs('account.membership') ? 'active' : '' }}"><i class="fa fa-crown me-1"></i> Hạng thành viên</a>
    <a href="{{ route('addresses.index') }}" class="{{ request()->routeIs('addresses.*') ? 'active' : '' }}"><i class="fa fa-location-dot me-1"></i> Sổ địa chỉ</a>
</nav>
