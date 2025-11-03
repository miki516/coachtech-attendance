<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Tokyo']);
    }

    private function actingUserAt(Carbon $when): User
    {
        Carbon::setTestNow($when);
        $user = User::factory()->create([
            'name'              => 'テスト太郎',
            'email'             => 'user@example.com',
            'password'          => Hash::make('password123'),
            'email_verified_at' => now(),
            'role'              => 'user',
        ]);
        $this->actingAs($user);
        return $user;
    }

    private function openAttendance(User $user, Carbon $when): Attendance
    {
        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $when->copy()->startOfDay(),
            'clock_in'  => $when->copy()->setTime(9, 0, 0),
            'clock_out' => null,
            'note'      => null,
        ]);
    }

    /** HTMLに <form action="URL"> が存在することを判定 */
    private function assertFormActionExists(string $html, string $url, string $msg = ''): void
    {
        $pattern = '~<form[^>]+action="' . preg_quote($url, '~') . '"~';
        $this->assertSame(1, preg_match($pattern, $html), $msg ?: "Form action not found: {$url}");
    }

    /** HTMLに <form action="URL"> が存在しないことを判定 */
    private function assertFormActionNotExists(string $html, string $url, string $msg = ''): void
    {
        $pattern = '~<form[^>]+action="' . preg_quote($url, '~') . '"~';
        $this->assertSame(0, preg_match($pattern, $html), $msg ?: "Form action unexpectedly found: {$url}");
    }

    /**
     * 退勤ボタンが正しく機能する
     * - 出勤中画面で「退勤」ボタン表示（actionで判定）
     * - POSTで退勤後、「お疲れ様でした。」表示・各ボタン非表示（actionで判定）
     * - DBに退勤時刻が記録される
     */
    public function test_clock_out_button_works_and_status_becomes_clocked_out()
    {
        // 18:00 に退勤する前提
        $fixed = Carbon::create(2025, 10, 22, 18, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);
        $att   = $this->openAttendance($user, $fixed);

        // 出勤中：退勤ボタン(form action)が見える
        $page = $this->get(route('user.attendance.punch'));
        $page->assertOk();
        $this->assertFormActionExists($page->getContent(), route('user.attendance.clockout', $att->id));

        // 退勤実行
        $resp = $this->post(route('user.attendance.clockout', $att->id));
        $resp->assertStatus(302); // 遷移
        // $resp->assertRedirect(route('user.attendance.punch'));

        // 退勤済UI：「お疲れ様でした。」表示、各ボタンの form action は存在しない
        $page = $this->get(route('user.attendance.punch'));
        $page->assertOk();
        $page->assertSee('お疲れ様でした。');

        $html = $page->getContent();
        $this->assertFormActionNotExists($html, route('user.attendance.clockout', $att->id));
        $this->assertFormActionNotExists($html, route('user.attendance.clockin'));

        // 休憩 in/out も無し（id特定せずパターンで網羅チェック）
        $this->assertSame(0, preg_match('~<form[^>]+action="[^"]*/attendance/break/(in|out)/[^"]*"~', $html));

        // DB：退勤時刻が保存されている（exact一致）
        $att->refresh();
        $this->assertNotNull($att->clock_out, 'clock_out が保存されていません');
        $this->assertTrue(
            Carbon::parse($att->clock_out)->equalTo($fixed),
            'clock_out が期待と一致しません: ' . $att->clock_out . ' != ' . $fixed->toDateTimeString()
        );
    }

    /** 退勤時刻が勤怠一覧画面で確認できる */
    public function test_clock_out_time_is_visible_on_attendance_list()
    {
        // 9:30 出勤 → 17:55 退勤
        $base  = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($base);
        $att   = $this->openAttendance($user, $base);

        // 退勤（17:55）
        Carbon::setTestNow($base->copy()->setTime(17, 55));
        $this->post(route('user.attendance.clockout', $att->id));

        // 一覧画面へ
        $list = $this->get('/attendance/list');
        $list->assertOk();
        $list->assertSee('17:55');

        // DB担保
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $user->id,
            'work_date' => $base->copy()->startOfDay()->toDateString(),
        ]);
    }
}
