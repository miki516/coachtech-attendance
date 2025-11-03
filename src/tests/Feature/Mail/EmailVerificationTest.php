<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-10-22 09:00:00');
        config(['app.locale' => 'ja']);
    }

    /** 会員登録後、認証メールが送信される */
    public function test_verification_email_is_sent_on_register()
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'verify@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** 誘導画面に「認証はこちらから」ボタン相当が表示される（UI確認） */
    public function test_verify_notice_page_shows_cta_button()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user);
        $res = $this->get(route('verification.notice'));
        $res->assertOk();
        // 文言はあなたの Blade の文言に合わせて変更してOK
        $res->assertSee('認証はこちらから');
    }

    /** 誘導画面から再送信すると認証メールが送られる */
    public function test_resend_verification_email_from_notice()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $res = $this->from(route('verification.notice'))
            ->post(route('verification.send'));

        // リダイレクトで戻る想定
        $res->assertRedirect(route('verification.notice'));

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    /** 署名付きリンクでメール認証を完了すると、勤怠打刻画面へ遷移する */
    public function test_signed_verification_link_completes_and_redirects_to_punch()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $res = $this->get($url);
        $res->assertRedirect(route('user.attendance.punch'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
