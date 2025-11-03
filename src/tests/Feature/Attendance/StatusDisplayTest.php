<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class StatusDisplayTest extends TestCase
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
            'email'             => 'test@example.com',
            'password'          => Hash::make('password123'),
            'email_verified_at' => now(),
            'role'              => 'user',
        ]);
        $this->actingAs($user);
        return $user;
    }

    /** HTML中に <form action="URL"> が存在することを厳密判定 */
    private function assertFormActionExists(string $html, string $url, string $msg = ''): void
    {
        $pattern = '~<form[^>]+action="' . preg_quote($url, '~') . '"~';
        $this->assertSame(1, preg_match($pattern, $html), $msg ?: "Form action not found: {$url}");
    }

    /** HTML中に <form action="URL"> が存在しないことを厳密判定 */
    private function assertFormActionNotExists(string $html, string $url, string $msg = ''): void
    {
        $pattern = '~<form[^>]+action="' . preg_quote($url, '~') . '"~';
        $this->assertSame(0, preg_match($pattern, $html), $msg ?: "Form action unexpectedly found: {$url}");
    }

    /** 勤務外：当日に出勤レコードなし → 出勤ボタンのみ表示 */
    public function test_off_duty_shows_only_clock_in_button()
    {
        $fixed = Carbon::create(2025, 10, 22, 9, 30, 0, 'Asia/Tokyo');
        $this->actingUserAt($fixed);

        $res = $this->get(route('user.attendance.punch'));
        $res->assertOk();

        $html = $res->getContent();

        // 出勤フォームはある
        $this->assertFormActionExists($html, route('user.attendance.clockin'));

        // 退勤/休憩系フォームは無い
        $this->assertFormActionNotExists($html, route('user.attendance.clockout', 1)); // idは部分一致回避のため後で総当り
        $this->assertFormActionNotExists($html, route('user.attendance.break.in', 1));
        $this->assertFormActionNotExists($html, route('user.attendance.break.out', 1));

        // 文言ベースの副次確認（誤検知防止でText版）
        $res->assertSeeText('出勤');
        $res->assertDontSeeText('休憩入');
        $res->assertDontSeeText('休憩戻');
    }

    /** 出勤中：open勤怠 → 退勤/休憩入は表示、休憩戻/出勤は非表示 */
    public function test_on_duty_shows_clock_out_and_break_in_but_not_break_out_or_clock_in()
    {
        $fixed = Carbon::create(2025, 10, 22, 10, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        $att = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->subHour(), // 9:00
            'clock_out' => null,
            'note'      => null,
        ]);

        $res = $this->get(route('user.attendance.punch'));
        $res->assertOk();
        $res->assertSeeText('出勤中');

        $html = $res->getContent();

        // 退勤/休憩入 あり
        $this->assertFormActionExists($html, route('user.attendance.clockout', $att->id));
        $this->assertFormActionExists($html, route('user.attendance.break.in', $att->id));

        // 休憩戻/出勤 なし
        $this->assertFormActionNotExists($html, route('user.attendance.break.out', $att->id));
        $this->assertFormActionNotExists($html, route('user.attendance.clockin'));
    }

    /** 休憩中：open勤怠＋open休憩 → 休憩戻のみ表示、休憩入/退勤/出勤は非表示 */
    public function test_on_break_shows_break_out_but_not_break_in_or_clock_out_or_clock_in()
    {
        $fixed = Carbon::create(2025, 10, 22, 12, 15, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        $att = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->subHours(3), // 9:15
            'clock_out' => null,
            'note'      => null,
        ]);

        BreakTime::create([
            'attendance_id' => $att->id,
            'break_start'   => $fixed->copy()->subMinutes(10), // 12:05
            'break_end'     => null,
        ]);

        $res = $this->get(route('user.attendance.punch'));
        $res->assertOk();
        $res->assertSeeText('休憩中');

        $html = $res->getContent();

        // 休憩戻のみ あり
        $this->assertFormActionExists($html, route('user.attendance.break.out', $att->id));

        // 休憩入/退勤/出勤 は なし
        $this->assertFormActionNotExists($html, route('user.attendance.break.in', $att->id));
        $this->assertFormActionNotExists($html, route('user.attendance.clockout', $att->id));
        $this->assertFormActionNotExists($html, route('user.attendance.clockin'));
    }

    /** 退勤済：close勤怠 → 「お疲れ様でした。」のみ、他ボタンは非表示 */
    public function test_clocked_out_shows_thanks_and_not_clock_in_or_break_buttons()
    {
        $fixed = Carbon::create(2025, 10, 22, 18, 0, 0, 'Asia/Tokyo');
        $user  = $this->actingUserAt($fixed);

        Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $fixed->copy()->startOfDay(),
            'clock_in'  => $fixed->copy()->subHours(8),   // 10:00
            'clock_out' => $fixed->copy()->subMinutes(5), // 17:55
            'note'      => null,
        ]);

        $res = $this->get(route('user.attendance.punch'));
        $res->assertOk();
        $res->assertSee('お疲れ様でした。');

        $html = $res->getContent();

        // 出勤/休憩（入/戻）/退勤のフォームはいずれも存在しない
        $this->assertFormActionNotExists($html, route('user.attendance.clockin'));
        // id不明でも “/attendance/clockout” / “/attendance/break/*” のフォームが無いことをゆるく確認したい場合は下記でもOK
        $this->assertSame(0, preg_match('~<form[^>]+action="[^"]*/attendance/clockout/[^"]*"~', $html));
        $this->assertSame(0, preg_match('~<form[^>]+action="[^"]*/attendance/break/(in|out)/[^"]*"~', $html));

        // テキスト誤検知回避の副次確認
        $res->assertDontSeeText('出勤');
        $res->assertDontSeeText('休憩入');
        $res->assertDontSeeText('休憩戻');
    }
}
