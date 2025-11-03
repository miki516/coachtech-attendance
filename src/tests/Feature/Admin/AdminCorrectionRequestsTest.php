<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminCorrectionRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $u1;
    private User $u2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2025, 10, 23, 9, 0, 0, 'Asia/Tokyo')); // 固定
        config(['app.locale' => 'ja']);

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
    }

    /** 承認待ちの修正申請が全て表示される（承認待ちタブ） */
    public function test_pending_requests_are_listed_for_admin()
    {
        // 2件: 承認待ち（pending）
        StampCorrectionRequest::create([
            'user_id'             => $this->u1->id,
            'attendance_id'       => null,
            'target_date'         => '2025-10-20',
            'requested_clock_in'  => '2025-10-20 09:05:00',
            'requested_clock_out' => '2025-10-20 18:10:00',
            'requested_breaks'    => [
                ['start' => '2025-10-20 12:05:00', 'end' => '2025-10-20 12:20:00'],
            ],
            'reason'              => '山田の申請：遅刻修正',
            'status'              => 'pending',
        ]);

        StampCorrectionRequest::create([
            'user_id'             => $this->u2->id,
            'attendance_id'       => null,
            'target_date'         => '2025-10-22',
            'requested_clock_in'  => '2025-10-22 10:00:00',
            'requested_clock_out' => '2025-10-22 19:00:00',
            'requested_breaks'    => [],
            'reason'              => '佐藤の申請：シフト変更',
            'status'              => 'pending',
        ]);

        // 参考：承認済み1件（リストに出るがタブは別）
        StampCorrectionRequest::create([
            'user_id'             => $this->u1->id,
            'attendance_id'       => null,
            'target_date'         => '2025-10-05',
            'requested_clock_in'  => '2025-10-05 09:00:00',
            'requested_clock_out' => '2025-10-05 18:00:00',
            'requested_breaks'    => [],
            'reason'              => '山田の過去申請：OK済み',
            'status'              => 'approved',
        ]);

        $res = $this->get(route('admin.request.index'));
        $res->assertOk();

        // 承認待ちセクションに pending の2件が見える想定（理由文などで確認）
        $res->assertSee('承認待ち');
        $res->assertSee('山田の申請：遅刻修正');
        $res->assertSee('佐藤の申請：シフト変更');
    }

    /** 承認済みの修正申請が全て表示される（承認済みタブ） */
    public function test_approved_requests_are_listed_for_admin()
    {
        StampCorrectionRequest::create([
            'user_id'             => $this->u1->id,
            'attendance_id'       => null,
            'target_date'         => '2025-10-05',
            'requested_clock_in'  => '2025-10-05 09:00:00',
            'requested_clock_out' => '2025-10-05 18:00:00',
            'requested_breaks'    => [],
            'reason'              => '山田の過去申請：OK済み',
            'status'              => 'approved',
        ]);

        $res = $this->get(route('admin.request.index'));
        $res->assertOk();

        $res->assertSee('承認済み');
        $res->assertSee('山田の過去申請：OK済み');
    }

    /** 修正申請の詳細内容が正しく表示される */
    public function test_request_detail_shows_correct_values()
    {
        $att = Attendance::factory()->for($this->u1)->create([
            'work_date' => '2025-10-21',
            'clock_in'  => '2025-10-21 09:00:00',
            'clock_out' => '2025-10-21 18:00:00',
        ]);

        $req = StampCorrectionRequest::create([
            'user_id'             => $this->u1->id,
            'attendance_id'       => $att->id,
            'target_date'         => '2025-10-21',
            'requested_clock_in'  => '2025-10-21 09:05:00',
            'requested_clock_out' => '2025-10-21 18:10:00',
            'requested_breaks'    => [
                ['start' => '2025-10-21 12:05:00', 'end' => '2025-10-21 12:20:00'],
            ],
            'reason'              => '昼休み延長のため',
            'status'              => 'pending',
        ]);

        $res = $this->get(route('admin.request.show', ['attendance_correct_request_id' => $req->id]));
        $res->assertOk();

        $res->assertSee('山田太郎'); // 申請者
        $res->assertSee('昼休み延長のため'); // 理由

        // 対象日の表示（年と月日のあいだに任意のタグが入ってもOK）
        $targetDate = Carbon::parse('2025-10-21', 'Asia/Tokyo')->locale('ja');

        $yearPart = $targetDate->copy()->format('Y') . '年';                 // 2025年
        $mdPart   = $targetDate->copy()->isoFormat('M月D日');               // 10月21日
        $ymdSlash = $targetDate->copy()->format('Y/m/d');                   // 2025/10/21
        $ymdKanji = $targetDate->copy()->format('Y年 n月 j日');             // 2025年 10月 21日
        $ymdDow   = $targetDate->copy()->isoFormat('YYYY年M月D日（ddd）');  // 2025年10月21日（火）
        $mdDow    = $targetDate->copy()->isoFormat('MM/DD(ddd)');           // 10/21(火)

        $html = $res->getContent();

        // 「2025年」+（任意の空白/タグ）+「10月21日」を許可（span分割対応）
        $patternSplit = '/'
            . preg_quote($yearPart, '/')
            . '\s*(?:<[^>]+>\s*)*'
            . preg_quote($mdPart, '/')
            . '/u';

        $candidates = [$ymdSlash, $ymdKanji, $ymdDow, $mdDow];

        $this->assertTrue(
            preg_match($patternSplit, $html) === 1
            || collect($candidates)->contains(fn($d) => str_contains($html, $d)),
            '日付表示が想定に合いませんでした。候補: '
            . implode(' / ', array_merge(
                ["{$yearPart}…{$mdPart}（タグ挟み可）"], $candidates
            ))
        );

        // 申請された時刻
        $res->assertSee('09:05');
        $res->assertSee('18:10');

        // 申請休憩（存在するなら）
        $res->assertSee('12:05');
        $res->assertSee('12:20');
    }

    /** 承認処理で勤怠が更新され、申請ステータスが approved になる */
    public function test_approve_request_updates_attendance_and_marks_approved()
    {
        $att = Attendance::factory()->for($this->u1)->create([
            'work_date' => '2025-10-22',
            'clock_in'  => '2025-10-22 09:00:00',
            'clock_out' => '2025-10-22 18:00:00',
        ]);

        $req = StampCorrectionRequest::create([
            'user_id'             => $this->u1->id,
            'attendance_id'       => $att->id,
            'target_date'         => '2025-10-22',
            'requested_clock_in'  => '2025-10-22 09:10:00',
            'requested_clock_out' => '2025-10-22 18:20:00',
            'requested_breaks'    => [
                ['start' => '2025-10-22 12:00:00', 'end' => '2025-10-22 12:30:00'],
                ['start' => '2025-10-22 15:00:00', 'end' => '2025-10-22 15:10:00'],
            ],
            'reason'              => '実績に合わせて調整',
            'status'              => 'pending',
        ]);

        // 承認POST
        $resp = $this->post(route('admin.request.approve', ['attendance_correct_request_id' => $req->id]));

        // 200 / 204 / 302 / 303 のいずれかを許容
        $code = $resp->getStatusCode();
        $this->assertTrue(
            in_array($code, [200, 204, 302, 303], true),
            "承認POSTのHTTPステータスが想定外です: {$code}"
        );

        // リダイレクトの場合はLocationヘッダの有無も確認
        if (in_array($code, [302, 303], true)) {
            $resp->assertRedirect();
        }

        // リフレッシュ
        $att->refresh();
        $req->refresh();

        // 勤怠が申請値で更新されている
        $this->assertEquals('09:10', Carbon::parse($att->clock_in)->format('H:i'));
        $this->assertEquals('18:20', Carbon::parse($att->clock_out)->format('H:i'));

        // 休憩も置き換え（合計2件想定）
        $this->assertCount(2, $att->breakTimes);
        $bt1 = $att->breakTimes[0];
        $bt2 = $att->breakTimes[1];
        $this->assertEquals('12:00', Carbon::parse($bt1->break_start)->format('H:i'));
        $this->assertEquals('12:30', Carbon::parse($bt1->break_end)->format('H:i'));
        $this->assertEquals('15:00', Carbon::parse($bt2->break_start)->format('H:i'));
        $this->assertEquals('15:10', Carbon::parse($bt2->break_end)->format('H:i'));

        // 申請ステータスが approved に変わる
        $this->assertEquals('approved', $req->status);
    }
}
