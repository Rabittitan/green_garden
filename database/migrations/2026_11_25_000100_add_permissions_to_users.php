<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phân quyền chi tiết cho nhân viên (chủ shop duyệt 25/11/2026). CHỈ THÊM 1 cột.
 * permissions = danh sách khóa App\Enums\StaffPermission; NULL = đủ mọi quyền nhân viên (giữ nguyên hành vi cũ
 * cho các tài khoản nhân viên đã có). Quản trị viên luôn có mọi quyền, cột này bỏ qua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
