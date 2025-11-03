<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ClockInTest extends TestCase
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

    private function assertFormActionExists(string $html, string $url, string $msg = ''): void
    {
        $pattern = '~<form[^>]+action="' . preg_quote($url, '~') . '"~';
        $this->assertSame(1, preg_match($pattern, $html), $msg ?: "Form action not found: {$url}");
    }

    private function assertFormActionNotExists(string $html, string $url, string $msg = ''): void
    {
        $pattern = '~<form[^>]+action="' . preg_quote($url, '~') . '"~';
        $this->assertSame(0, preg_match($pattern, $html), $msg ?: "Form action unexpectedly found: {$url}");
    }

    /**
     * 出勤ボタンが正しく機能する
     * 1) 勤務外で「出勤」ボタンが見える
     * 2) POSTで出勤 → 勤務中UI（退勤/休憩入の form が存在、出勤の form は存在しない）
     * 3) DBに出勤時刻が記録される
     */
    public function test_clock_in_button_works_and_status_becomes_on_duty()
    {
        $fixed = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        // 勤怠打刻画面（勤務外）
        $response = $this->get('/attendance');
        $response->assertOk();
        $response->assertSee('出勤');
        $response->assertDontSee('退勤');
        $response->assertDontSee('休憩入');
        $response->assertDontSee('休憩戻');

        // 出勤実行（POST /attendance）
        $response = $this->post('/attendance', []);
        $response->assertStatus(302);

        // 成功後の画面
        $page = $this->get('/attendance');
        $page->assertOk();

        // ステータス（例：出勤中）を一応確認
        $page->assertSee('出勤中');

        // 直近の open 勤怠を取得して action URL を確定
        $open = Attendance::where('user_id', $user->id)->whereNull('clock_out')->latest('id')->first();
        $this->assertNotNull($open, '出勤後の open 勤怠が見つかりません');

        $html = $page->getContent();

        // 退勤/休憩入の form が存在
        $this->assertFormActionExists($html, route('user.attendance.clockout', $open->id));
        $this->assertFormActionExists($html, route('user.attendance.break.in', $open->id));

        // 出勤の form は存在しない（「出勤中」による部分一致誤爆を避ける）
        $this->assertFormActionNotExists($html, route('user.attendance.clockin'));

        // DBに出勤時刻が正しく記録
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay()->toDateString(),
        ]);

        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);

        // clock_in が固定時刻で保存されているか
        $this->assertTrue(
            Carbon::parse($attendance->clock_in)->equalTo($fixed),
            'clock_in が期待と一致しません: ' . $attendance->clock_in . ' != ' . $fixed->toDateTimeString()
        );
    }

    /**
     * 出勤は一日一回のみ：退勤済ユーザーは同日に再出勤できない（出勤ボタン非表示）
     */
    public function test_clock_in_button_is_hidden_when_already_clocked_out_today()
    {
        $fixed = Carbon::create(2025, 10, 22, 18, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->setTime(9, 0),
            'clock_out' => $fixed->copy()->setTime(17, 0),
            'note'      => null,
        ]);

        $page = $this->get('/attendance');
        $page->assertOk();
        $page->assertSee('お疲れ様でした。');

        // フォーム action で「出勤」が無いことを厳密確認
        $this->assertFormActionNotExists($page->getContent(), route('user.attendance.clockin'));
    }

    /**
     * 出勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_in_time_is_visible_on_attendance_list()
    {
        $fixed = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        // 出勤
        $this->post('/attendance');

        // 一覧画面へ
        $list = $this->get('/attendance/list');
        $list->assertOk();

        // 表示時刻（H:i 想定）
        $list->assertSee($fixed->format('H:i'));

        // DB側の担保
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay()->toDateString(),
        ]);
    }
}
