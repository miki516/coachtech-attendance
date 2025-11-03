<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    // 打刻画面：現在の状態とボタン可否を判定
    public function showPunch()
    {
        $user  = Auth::user();
        $now   = Carbon::now();
        $today = $now->toDateString();

        // 出勤中（clock_out=null）の最新行
        $open = Attendance::where('user_id', $user->id)
            ->whereNull('clock_out')->latest('clock_in')->first();

        // 当日以外の開きっぱなしは無効扱い
        if ($open && $open->work_date?->toDateString() !== $today) $open = null;

        // 出勤中が無い時だけ、当日退勤済みを評価
        $todayClosed = !$open && Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)->whereNotNull('clock_out')->exists();

        // 休憩中判定（出勤中の行に紐づく未終了休憩）
        $break = $open
            ? $open->breakTimes()->whereNull('break_end')->latest('break_start')->first()
            : null;

        // 表示ステータス
        $status = $break ? '休憩中' : ($open ? '出勤中' : ($todayClosed ? '退勤済' : '勤務外'));

        return view('user.attendance.punch', [
            'now'         => $now,
            'status'      => $status,
            'open'        => $open,
            'canClockIn'  => !$open && !$todayClosed,
            'canClockOut' => (bool) $open,
            'canBreakIn'  => (bool) $open && !$break,
            'canBreakOut' => (bool) $break,
        ]);
    }

    // 出勤：同一日の二重出勤を防いで当日の行を再利用
    public function storePunch()
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $alreadyOpenToday = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->whereNotNull('clock_in')->whereNull('clock_out')->exists();
        if ($alreadyOpenToday) return back()->with('error', 'すでに出勤中です');

        $rec = Attendance::firstOrNew(['user_id' => $user->id, 'work_date' => $today]);
        if (!$rec->clock_in) {
            $rec->clock_in  = Carbon::now();
            $rec->clock_out = null; // 念のため初期化
            $rec->save();
        }
        return redirect()->route('user.attendance.punch');
    }

    // 退勤：本人チェック＆二重退勤防止
    public function clockOut(Attendance $attendance)
    {
        if ($attendance->user_id !== Auth::id()) abort(403);
        if (!$attendance->clock_out) $attendance->update(['clock_out' => Carbon::now()]);
        return redirect()->route('user.attendance.punch')->with('punch_done', 'お疲れ様でした。');
    }

    // 月次一覧：YYYY-MM 指定。各日の勤務/休憩合計を整形
    public function index(Request $request)
    {
        $user = Auth::user();
        $monthStr = $request->query('month');
        $cursor = $monthStr ? Carbon::createFromFormat('Y-m-d', "{$monthStr}-01") : Carbon::now();
        $start = $cursor->copy()->startOfMonth();
        $end   = $cursor->copy()->endOfMonth();

        // 当月の自分の勤怠（work_date をキー化）
        $byDate = Attendance::with('breakTimes')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')->get()
            ->keyBy(fn ($a) => $a->work_date->toDateString());

        $rows = $this->buildMonthRows($start, $end, $byDate);

        return view('user.attendance.index', [
            'rows'         => $rows,
            'cursor'       => $cursor,
            'prevMonth'    => $start->copy()->subMonth()->format('Y-m'),
            'nextMonth'    => $end->copy()->addMonth()->format('Y-m'),
            'nextDisabled' => $cursor->isSameMonth(Carbon::now()),
        ]);
    }

    // 勤怠詳細：承認待ちがあれば申請値を優先して表示
    public function show(Attendance $attendance)
    {
        if ($attendance->user_id !== Auth::id()) abort(403);

        $requestRec = StampCorrectionRequest::where('user_id', Auth::id())
            ->where('attendance_id', $attendance->id)
            ->latest('created_at')->first();

        // 対象日：work_date → 申請 target_date → clock_in → 今日
        if ($attendance->work_date) {
            $day = $attendance->work_date->copy()->startOfDay();
        } elseif ($requestRec?->target_date) {
            $day = Carbon::parse($requestRec->target_date)->startOfDay();
        } elseif ($attendance->clock_in) {
            $day = $attendance->clock_in->copy()->startOfDay();
        } else {
            $day = Carbon::now()->startOfDay();
        }

        // 申請が無い場合は対象日で検索（互換）
        if (!$requestRec) {
            $requestRec = StampCorrectionRequest::where('user_id', Auth::id())
                ->whereDate('target_date', $day)->latest('created_at')->first();
        }

        $isPending = $requestRec?->status === StampCorrectionRequest::STATUS_PENDING;

        // 備考は承認待ちなら申請理由、なければDBのnote
        $displayNote = $isPending ? ($requestRec->reason ?? '') : ($attendance->note ?? '');

        // 出退勤（承認待ちは申請値を優先）
        $displayClockIn  = $attendance->clock_in;
        $displayClockOut = $attendance->clock_out;
        if ($isPending) {
            if ($requestRec?->requested_clock_in)  $displayClockIn  = Carbon::parse($requestRec->requested_clock_in);
            if ($requestRec?->requested_clock_out) $displayClockOut = Carbon::parse($requestRec->requested_clock_out);
        }

        // 休憩（承認待ちは申請リストを優先）
        $displayBreaks = ($isPending && !empty($requestRec?->requested_breaks))
            ? collect($requestRec->requested_breaks)->map(fn ($b) => (object) [
                'break_start' => !empty($b['start']) ? Carbon::parse($b['start']) : null,
                'break_end'   => !empty($b['end'])   ? Carbon::parse($b['end'])   : null,
            ])
            : ($attendance->breakTimes?->sortBy('break_start')->values() ?? collect());

        return view('user.attendance.show', [
            'date'            => $day,
            'attendance'      => $attendance,
            'isPending'       => $isPending,
            'request'         => $requestRec,
            'displayClockIn'  => $displayClockIn,
            'displayClockOut' => $displayClockOut,
            'displayBreaks'   => $displayBreaks,
            'displayNote'     => $displayNote,
        ]);
    }

    // 月内各日の行データを生成（休憩は両端揃いのみ合算）
    private function buildMonthRows(Carbon $start, Carbon $end, $byDate): array
    {
        $rows = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $key = $day->toDateString();
            $rec = $byDate[$key] ?? null;

            $breakMin = ($rec && $rec->relationLoaded('breakTimes'))
                ? $rec->breakTimes->sum(fn ($b) =>
                    ($b->break_start && $b->break_end)
                        ? $b->break_end->diffInMinutes($b->break_start)
                        : 0)
                : 0;

            $workMin = ($rec && $rec->clock_in && $rec->clock_out)
                ? max($rec->clock_out->diffInMinutes($rec->clock_in) - $breakMin, 0)
                : 0;

            $hasAttendance = $rec && ($rec->clock_in || $rec->clock_out || ($rec->breakTimes && $rec->breakTimes->isNotEmpty()));
            $breakStr = $hasAttendance ? sprintf('%d:%02d', intdiv($breakMin, 60), $breakMin % 60) : null;
            $workStr  = ($rec && $rec->clock_out) ? sprintf('%d:%02d', intdiv($workMin, 60), $workMin % 60) : null;

            $rows[] = [
                'day'       => $day->copy(),
                'rec'       => $rec,
                'break_min' => $breakMin,
                'work_min'  => $workMin,
                'break_str' => $breakStr,
                'work_str'  => $workStr,
            ];
        }
        return $rows;
    }
}
