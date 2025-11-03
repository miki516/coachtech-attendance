<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Tokyo']);
    }

    private function actingAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => '管理者太郎',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);
        return $admin;
    }

    private function makeUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);
    }

    private function makeAttendance(User $user, string $date, string $in, ?string $out): Attendance
    {
        $d = Carbon::parse($date, 'Asia/Tokyo');
        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $d->copy()->startOfDay(),
            'clock_in'  => $d->copy()->setTimeFromTimeString($in),
            'clock_out' => $out ? $d->copy()->setTimeFromTimeString($out) : null,
            'note'      => null,
        ]);
    }

    /** 勤怠詳細画面に選択したレコードの内容が表示される */
    public function test_show_displays_selected_attendance_details()
    {
        $this->actingAdmin();
        Carbon::setTestNow(Carbon::create(2025, 10, 22, 9, 0, 0, 'Asia/Tokyo'));

        $u1 = $this->makeUser('山田花子', 'hanako@example.com');
        $u2 = $this->makeUser('田中太郎', 'taro@example.com');

        $a1 = $this->makeAttendance($u1, '2025-10-21', '09:00:00', '18:00:00');
        $a2 = $this->makeAttendance($u2, '2025-10-22', '10:15:00', '19:30:00');

        $res = $this->get(route('admin.attendance.show', ['attendance' => $a2->id]));
        $res->assertOk();

        // 表示内容の確認（氏名・日付・時刻）
        $res->assertSee('田中太郎');
        $candidates = [
            Carbon::parse('2025-10-22')->locale('ja')->isoFormat('YYYY年M月D日（ddd）'),
            Carbon::parse('2025-10-22')->locale('ja')->isoFormat('YYYY年M月D日'),
            '2025/10/22',
            '2025-10-22',
        ];
        $html = $res->getContent();
        $this->assertTrue(
            collect($candidates)->contains(fn($s) => str_contains($html, $s)),
            '詳細に対象日が表示されていません（候補: '.implode(' / ', $candidates).'）'
        );
        $res->assertSee('10:15');
        $res->assertSee('19:30');

        // もう一方のレコード固有の時刻が混在しないこと（簡易担保）
        $res->assertDontSee('09:00');
        $res->assertDontSee('18:00');
    }

    /**
     * 出勤>退勤（または退勤<出勤）でエラー
     * 期待文言：出勤時間もしくは退勤時間が不適切な値です
     */
    public function test_update_rejects_clock_in_after_clock_out()
    {
        $this->actingAdmin();
        $u = $this->makeUser('対象ユーザー', 'target@example.com');
        $att = $this->makeAttendance($u, '2025-10-22', '09:00:00', '18:00:00');

        $from = route('admin.attendance.show', ['attendance' => $att->id]);

        $resp = $this->from($from)->patch(route('admin.attendance.update', ['attendance' => $att->id]), [
            'clock_in'  => '19:00',
            'clock_out' => '18:00',
            'breaks'    => [],
            'note'      => '修正お願いします',
        ]);
        $resp->assertRedirect($from);

        $errors = session('errors')->getMessages();
        $bag = array_merge($errors['clock_in'] ?? [], $errors['clock_out'] ?? []);
        $this->assertTrue(
            collect($bag)->contains(fn ($m) => str_contains($m, '出勤時間もしくは退勤時間が不適切な値です')),
            '相関エラーが想定文言で返っていません'
        );
    }

    /**
     * 休憩開始が出勤より前 or 退勤より後 → 休憩時間が不適切な値です
     */
    public function test_update_rejects_break_start_invalid_boundaries()
    {
        $this->actingAdmin();
        $u = $this->makeUser('対象ユーザー', 'target@example.com');
        $att = $this->makeAttendance($u, '2025-10-22', '09:00:00', '18:00:00');

        $from = route('admin.attendance.show', ['attendance' => $att->id]);

        // 出勤より前
        $resp1 = $this->from($from)->patch(route('admin.attendance.update', ['attendance' => $att->id]), [
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            'breaks'    => [['start' => '08:30', 'end' => '09:05']],
            'note'      => '修正お願いします',
        ]);
        $resp1->assertRedirect($from);
        $resp1->assertSessionHasErrors(['breaks.0.start' => '休憩時間が不適切な値です']);

        // 退勤より後
        $resp2 = $this->from($from)->patch(route('admin.attendance.update', ['attendance' => $att->id]), [
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            'breaks'    => [['start' => '18:30', 'end' => null]],
            'note'      => '修正お願いします',
        ]);
        $resp2->assertRedirect($from);
        $resp2->assertSessionHasErrors(['breaks.0.start' => '休憩時間が不適切な値です']);
    }

    /**
     * 休憩終了が退勤より後 → 休憩時間もしくは退勤時間が不適切な値です
     */
    public function test_update_rejects_break_end_after_clock_out()
    {
        $this->actingAdmin();
        $u = $this->makeUser('対象ユーザー', 'target@example.com');
        $att = $this->makeAttendance($u, '2025-10-22', '09:00:00', '18:00:00');

        $from = route('admin.attendance.show', ['attendance' => $att->id]);

        $resp = $this->from($from)->patch(route('admin.attendance.update', ['attendance' => $att->id]), [
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            'breaks'    => [['start' => '17:30', 'end' => '19:00']],
            'note'      => '修正お願いします',
        ]);
        $resp->assertRedirect($from);
        $resp->assertSessionHasErrors(['breaks.0.end' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    /** 備考未入力 → 備考を記入してください */
    public function test_update_requires_note()
    {
        $this->actingAdmin();
        $u = $this->makeUser('対象ユーザー', 'target@example.com');
        $att = $this->makeAttendance($u, '2025-10-22', '09:00:00', '18:00:00');

        $from = route('admin.attendance.show', ['attendance' => $att->id]);

        $resp = $this->from($from)->patch(route('admin.attendance.update', ['attendance' => $att->id]), [
            'clock_in'  => '09:05',
            'clock_out' => '18:10',
            'breaks'    => [],
            'note'      => '',
        ]);
        $resp->assertRedirect($from);
        $resp->assertSessionHasErrors(['note' => '備考を記入してください']);
    }

    /** 成功更新：時刻が更新され、詳細画面で反映される */
    public function test_update_succeeds_and_reflects_on_detail()
    {
        $this->actingAdmin();
        $u = $this->makeUser('対象ユーザー', 'target@example.com');
        $att = $this->makeAttendance($u, '2025-10-22', '09:00:00', '18:00:00');

        $to = [
            'date'      => '2025-10-22', // ← 追加（= $att->work_date の Y-m-d）
            'clock_in'  => '09:10',
            'clock_out' => '18:20',
            'breaks'    => [['start' => '12:00', 'end' => '12:20']],
            'note'      => '管理者が修正',
        ];

        $resp = $this->patch(route('admin.attendance.update', ['attendance' => $att->id]), $to);
        // 成功時の遷移先は詳細画面に統一する
        $resp->assertRedirect(route('admin.attendance.show', ['attendance' => $att->id]));

        // 反映確認
        $show = $this->get(route('admin.attendance.show', ['attendance' => $att->id]));
        $show->assertOk();
        $show->assertSee('09:10');
        $show->assertSee('18:20');

        // DB 反映の最低限担保
        $att->refresh();
        $this->assertEquals('09:10', Carbon::parse($att->clock_in)->format('H:i'));
        $this->assertEquals('18:20', Carbon::parse($att->clock_out)->format('H:i'));
    }
}
