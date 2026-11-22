<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Account\EmailChanged;
use App\Notifications\Account\PasswordChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** K2: quên mật khẩu + mail bảo mật (đổi mật khẩu, đổi email). */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'MatKhauCu123';

    private const NEW = 'MatKhauMoi456';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function user(bool $active = true): User
    {
        $u = User::factory()->create(['email' => 'quen.mk@greenlife.test', 'password' => Hash::make(self::OLD)]);
        $u->forceFill(['is_active' => $active])->save();

        return $u;
    }

    /** Gửi yêu cầu, trả về token thật đã nằm trong mail (lấy từ notification, không đọc DB). */
    private function requestToken(User $user): string
    {
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    /** @return TestResponse<Response> */
    private function resetWith(string $email, string $token, string $password = self::NEW): TestResponse
    {
        return $this->post(route('password.update'), ['email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password]);
    }

    public function test_pages_and_login_link(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'), false)->assertSee('Quên mật khẩu?');
        $this->get(route('password.request'))->assertOk()->assertSee('Quên mật khẩu')->assertSee('name="_token"', false);
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'a@b.vn']))->assertOk()
            ->assertSee('value="abc"', false)->assertSee('value="a@b.vn"', false);
    }

    public function test_existing_unknown_and_locked_emails_get_the_same_answer(): void
    {
        $active = $this->user();
        $locked = User::factory()->create(['email' => 'bi.khoa@greenlife.test']);
        $locked->forceFill(['is_active' => false])->save();

        $answers = [];
        foreach ([$active->email, 'khong.ton.tai@greenlife.test', $locked->email] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertRedirect();
            $answers[] = session('success');
        }

        $this->assertCount(1, array_unique($answers), 'Thông báo phải giống hệt nhau, không lộ email nào có tài khoản');
        $this->assertSame(__('passwords.sent'), $answers[0]);
        Notification::assertSentTo($active, ResetPassword::class);
        Notification::assertNotSentTo($locked, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_reset_mail_is_vietnamese_and_links_to_the_reset_page(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);
        $mail = (new ResetPassword($token))->toMail($user);

        $this->assertSame('Đặt lại mật khẩu - GreenGarden', $mail->subject);
        $this->assertStringContainsString(route('password.reset', ['token' => $token, 'email' => $user->email]), $mail->actionUrl);
        $this->assertStringContainsString('hết hạn sau 60 phút', implode(' ', $mail->outroLines));
        $this->assertStringNotContainsString('Reset Password', (string) $mail->render());
    }

    public function test_full_reset_flow(): void
    {
        $user = $this->user();
        $oldRemember = $user->remember_token;
        $token = $this->requestToken($user);

        $this->resetWith($user->email, $token)->assertRedirect(route('login'))->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW, $user->password));
        $this->assertNotSame($oldRemember, $user->remember_token, 'Cookie "ghi nhớ đăng nhập" cũ phải mất hiệu lực');
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count(), 'Token phải bị xóa sau khi dùng');
        Notification::assertSentTo($user, PasswordChanged::class, fn ($n) => $n->viaReset === true);

        $this->post(route('login.post'), ['email' => $user->email, 'password' => self::OLD])->assertSessionHasErrors('email');
        $this->post(route('login.post'), ['email' => $user->email, 'password' => self::NEW])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_token_cannot_be_reused_expired_or_used_with_another_email(): void
    {
        $user = $this->user();
        $other = User::factory()->create(['email' => 'nguoi.khac@greenlife.test']);
        $token = $this->requestToken($user);

        // Sai email (dù email đó có tài khoản) -> thông báo chung, không đổi mật khẩu ai
        $this->resetWith($other->email, $token)->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->resetWith($user->email, $token)->assertRedirect(route('login'));
        // Dùng lại link -> từ chối
        $this->resetWith($user->email, $token, 'MatKhauKhac789')->assertSessionHasErrors(['email' => __('passwords.token')]);
        $this->assertTrue(Hash::check(self::NEW, $user->refresh()->password));

        // Link hết hạn (quá 60 phút)
        $this->travel(2)->minutes();                                           // qua thời gian chờ gửi lại của broker
        $fresh = $this->requestToken($user);
        $this->travel(61)->minutes();
        $this->resetWith($user->email, $fresh, 'MatKhauKhac789')->assertSessionHasErrors(['email' => __('passwords.token')]);
        $this->assertTrue(Hash::check(self::NEW, $user->refresh()->password));
    }

    public function test_account_locked_after_link_was_sent_cannot_reset(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);
        $user->forceFill(['is_active' => false])->save();

        $this->resetWith($user->email, $token)->assertSessionHasErrors(['email' => __('passwords.token')]);
        $this->assertTrue(Hash::check(self::OLD, $user->refresh()->password));
    }

    public function test_weak_or_mismatched_password_is_rejected_in_vietnamese(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);

        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'abc', 'password_confirmation' => 'xyz'])
            ->assertSessionHasErrors(['password']);
        $this->assertStringContainsString('Mật khẩu', session('errors')->first('password'));
        $this->assertTrue(Hash::check(self::OLD, $user->refresh()->password));
    }

    public function test_reset_logs_out_every_other_session(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);
        config(['session.driver' => 'database']);
        foreach (['phien-dien-thoai', 'phien-may-tinh'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }
        DB::table('sessions')->insert(['id' => 'phien-khach-khac', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()]);

        $this->resetWith($user->email, $token)->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'phien-khach-khac')->count(), 'Không được đăng xuất người khác');
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "do{$i}@greenlife.test"])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'do6@greenlife.test'])->assertStatus(429);
    }

    public function test_logged_in_users_cannot_open_forgot_password(): void
    {
        $this->actingAs($this->user())->get(route('password.request'))->assertRedirect();
    }

    public function test_changing_password_in_profile_sends_security_email(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHas('success');

        Notification::assertSentTo($user, PasswordChanged::class, fn ($n) => $n->viaReset === false);
        $mail = (new PasswordChanged(now()))->toMail($user);
        $this->assertStringContainsString('thay đổi trong trang tài khoản', implode(' ', $mail->introLines));
    }

    public function test_changing_email_warns_the_old_address(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put(route('profile.update'), ['name' => $user->name, 'email' => 'email.moi@gmail.com', 'phone' => null])
            ->assertRedirect(route('verification.notice'));

        Notification::assertSentOnDemand(EmailChanged::class, function (EmailChanged $n, array $channels, object $notifiable) {
            return $notifiable->routes['mail'] === 'quen.mk@greenlife.test' && $n->newEmail === 'email.moi@gmail.com';
        });
        $text = implode(' ', (new EmailChanged('A', 'email.moi@gmail.com', now()))->toMail(new \stdClass)->introLines);
        $this->assertStringContainsString('em*******@gmail.com', $text, 'Email mới phải được che bớt');
        $this->assertStringNotContainsString('email.moi@gmail.com', $text);
    }

    public function test_profile_update_without_email_change_sends_nothing(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put(route('profile.update'), ['name' => 'Tên mới', 'email' => $user->email, 'phone' => null])
            ->assertSessionHas('success');
        Notification::assertNothingSent();
    }

    public function test_mask(): void
    {
        $this->assertSame('ng*******@gmail.com', EmailChanged::mask('nguyenvan@gmail.com'));
        $this->assertSame('a***@x.vn', EmailChanged::mask('ab@x.vn'));
        $this->assertSame('a***@x.vn', EmailChanged::mask('a@x.vn'));
    }
}
