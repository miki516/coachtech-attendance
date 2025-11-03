<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $u1;
    private User $u2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2025, 10, 23, 9, 0, 0, 'Asia/Tokyo')); // 2025/10 固定
        $this->admin = User::factory()->create([
            'name'  => '管理者',
            'email' => 'admin@example.com',
            'role'  => 'admin',
        ]);
        $this->u1 = User::factory()->create([
            'name'  => '山田太郎',
            'email' => 'taro@example.com',
            'role'  => 'user',
        ]);
        $this->u2 = User::factory()->create([
            'name'  => '佐藤花子',
            'email' => 'hanako@example.com',
            'role'  => 'user',
        ]);
        $this->actingAs($this->admin);
        config(['app.locale' => 'ja']);
    }

    /** スタッフ一覧で一般ユーザーの氏名・メールが見える */
    public function test_admin_can_see_all_general_users_name_and_email_in_staff_index()
    {
        $res = $this->get(route('admin.staff.index'));
        $res->assertOk();

        // 一般ユーザーが表示される
        $res->assertSee('山田太郎');
        $res->assertSee('taro@example.com');
        $res->assertSee('佐藤花子');
        $res->assertSee('hanako@example.com');

        // 管理者自身はスタッフ一覧に出ない（出る仕様ならこの行は削除してOK）
        $res->assertDontSee('admin@example.com');
    }

    /** スタッフ別勤怠一覧：当月の勤怠が正しく表示される */
    public function test_staff_attendance_list_shows_correct_month_records()
    {
        // 当月（2025-10）2件
        Attendance::factory()->for($this->u1)->create([
            'work_date' => Carbon::create(2025, 10, 10, 0, 0, 0, 'Asia/Tokyo'),
            'clock_in'  => Carbon::create(2025, 10, 10, 9, 5, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2025, 10, 10, 18, 0, 0, 'Asia/Tokyo'),
        ]);
        Attendance::factory()->for($this->u1)->create([
            'work_date' => Carbon::create(2025, 10, 21, 0, 0, 0, 'Asia/Tokyo'),
            'clock_in'  => Carbon::create(2025, 10, 21, 10, 15, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2025, 10, 21, 19, 30, 0, 'Asia/Tokyo'),
        ]);
        // 別月（2025-09）1件（当月画面では出ない想定）
        Attendance::factory()->for($this->u1)->create([
            'work_date' => Carbon::create(2025, 9, 25, 0, 0, 0, 'Asia/Tokyo'),
            'clock_in'  => Carbon::create(2025, 9, 25, 7, 11, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2025, 9, 25, 16, 22, 0, 'Asia/Tokyo'),
        ]);

        // 当月表示（?month=YYYY-MM）
        $res = $this->get(route('admin.staff.show', ['staff' => $this->u1->id]) . '?month=2025-10');
        $res->assertOk();

        // ヘッダに当月（Y/m）
        $res->assertSee('2025/10');

        // 当月の2件が見える（一覧は MM/DD(ddd) 表示）
        $res->assertSee(Carbon::create(2025,10,10)->locale('ja')->isoFormat('MM/DD(ddd)'));
        $res->assertSee('09:05');
        $res->assertSee('18:00');

        $res->assertSee(Carbon::create(2025,10,21)->locale('ja')->isoFormat('MM/DD(ddd)'));
        $res->assertSee('10:15');
        $res->assertSee('19:30');

        // 前月の表示は当月画面では出さない想定
        $res->assertDontSee('07:11');
        $res->assertDontSee('16:22');
    }

    /** 「前月」ナビで前月データに切り替わる（?month= 前月） */
    public function test_staff_attendance_list_moves_to_prev_month()
    {
        Attendance::factory()->for($this->u1)->create([
            'work_date' => Carbon::create(2025, 9, 25, 0, 0, 0, 'Asia/Tokyo'),
            'clock_in'  => Carbon::create(2025, 9, 25, 7, 11, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2025, 9, 25, 16, 22, 0, 'Asia/Tokyo'),
        ]);

        $res = $this->get(route('admin.staff.show', ['staff' => $this->u1->id]) . '?month=2025-09');
        $res->assertOk();
        $res->assertSee('2025/09');
        // 一覧は MM/DD(ddd) で出している想定に合わせる
        $res->assertSee(Carbon::create(2025,9,25)->locale('ja')->isoFormat('MM/DD(ddd)'));
        $res->assertSee('07:11');
        $res->assertSee('16:22');
    }

    /** 「翌月」ナビで翌月データに切り替わる（?month= 翌月） */
    public function test_staff_attendance_list_moves_to_next_month()
    {
        Attendance::factory()->for($this->u1)->create([
            'work_date' => Carbon::create(2025, 11, 2, 0, 0, 0, 'Asia/Tokyo'),
            'clock_in'  => Carbon::create(2025, 11, 2, 8, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2025, 11, 2, 17, 0, 0, 'Asia/Tokyo'),
        ]);

        $res = $this->get(route('admin.staff.show', ['staff' => $this->u1->id]) . '?month=2025-11');
        $res->assertOk();
        $res->assertSee('2025/11');
        $res->assertSee(Carbon::create(2025,11,2)->locale('ja')->isoFormat('MM/DD(ddd)'));
        $res->assertSee('08:00');
        $res->assertSee('17:00');
    }

    /** 「詳細」押下でその日の勤怠詳細へ遷移（by-dateブリッジ想定） */
    public function test_detail_link_moves_to_attendance_detail()
    {
        $target = '2025-10-12';
        // 管理者用：/admin/attendance/detail/by-date/{staff}/{date}
        $res = $this->get(route('admin.attendance.show.by_date', [
            'staff' => $this->u1->id,
            'date'  => $target,
        ]));
        // リダイレクトで /admin/attendance/{attendance} へ
        $res->assertRedirect();

        $follow = $this->get($res->headers->get('Location'));
        $follow->assertOk();

        // ユーザー名は見える
        $follow->assertSee('山田太郎');

        // 詳細ページはUI要件に合わせて「年」と「月日」を別セル表示
        $c = Carbon::parse($target)->locale('ja');
        $year     = $c->format('Y').'年';     // 例: 2025年
        $monthDay = $c->isoFormat('M月D日');  // 例: 10月12日

        $follow->assertSee($year);
        $follow->assertSee($monthDay);
    }
}
