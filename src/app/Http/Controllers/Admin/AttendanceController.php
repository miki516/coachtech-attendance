<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    // 勤怠一覧を表示
    public function index(Request $request)
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $attendances = Attendance::with(['user', 'breakTimes'])
            ->whereDate('work_date', $date)
            ->orderBy('user_id')
            ->get();
        $users = User::where('role', 'user')->get();

        // 時刻フォーマット用のショート関数
        $format = fn($time) => $time ? Carbon::parse($time)->format('H:i') : '';
        $minutes = fn($a, $b) => Carbon::parse($b)->diffInMinutes(Carbon::parse($a));

        // 表示用に整形
        $rows = $users->map(function ($user) use ($attendances, $format, $minutes) {
            $att = $attendances->firstWhere('user_id', $user->id);
            $clockIn  = $format($att?->clock_in);
            $clockOut = $format($att?->clock_out);
            $totalBreakMin = $att?->breakTimes->sum(
                fn($b) => $b->break_start && $b->break_end ? $minutes($b->break_start, $b->break_end) : 0
            ) ?? 0;

            // 実勤務があるか（出勤 or 退勤 or 休憩あり）
            $hasAttendance = $att && (
                $att->clock_in ||
                $att->clock_out ||
                ($att->breakTimes && $att->breakTimes->isNotEmpty())
            );

            $breakTime = $hasAttendance
                ? sprintf('%d:%02d', intdiv($totalBreakMin, 60), $totalBreakMin % 60)
                : '';

            $totalWork = '';
            if ($att?->clock_in && $att?->clock_out) {
                $totalMin = max(
                    Carbon::parse($att->clock_out)->diffInMinutes(Carbon::parse($att->clock_in)) - $totalBreakMin,
                    0
                );
                $totalWork = sprintf('%d:%02d', intdiv($totalMin, 60), $totalMin % 60);
            }

            return [
                'name'          => $user->name,
                'clock_in'      => $clockIn,
                'clock_out'     => $clockOut,
                'break'         => $breakTime,
                'total'         => $totalWork,
                'user_id'       => $user->id,
                'attendance_id' => $att?->id,
            ];
        });

        return view('admin.attendance.index', [
            'date'     => $date,
            'rows'     => $rows,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
        ]);
    }

    // 勤怠詳細
    public function show(Attendance $attendance)
    {
        $requestRec = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->latest('created_at')->first();

        $isPending = $requestRec?->status === StampCorrectionRequest::STATUS_PENDING;

        // 対象日（優先順位：work_date > 申請target_date > clock_in > 現在）
        $date = $attendance->work_date
            ?? ($requestRec?->target_date ? Carbon::parse($requestRec->target_date) : null)
            ?? $attendance->clock_in
            ?? now();
        $date = $date->copy()->startOfDay();

        // 申請値（旧キー互換）
        $reqIn  = $requestRec->requested_clock_in  ?? $requestRec->clock_in_time  ?? null;
        $reqOut = $requestRec->requested_clock_out ?? $requestRec->clock_out_time ?? null;
        $reqBrs = $requestRec->requested_breaks    ?? $requestRec->break_times    ?? null;

        // 文字列や"HH:mm"形式をCarbon化
        $toCarbon = function ($val) use ($date) {
            if (!$val) return null;
            if ($val instanceof Carbon) return $val;
            $s = trim((string)$val);
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) {
                [$h, $m, $sec] = array_pad(explode(':', $s), 3, 0);
                return $date->copy()->setTime((int)$h, (int)$m, (int)$sec);
            }
            return Carbon::parse($s);
        };

        // 表示用の出退勤（承認待ちは申請値を優先）
        $displayClockIn  = $attendance->clock_in;
        $displayClockOut = $attendance->clock_out;
        if ($isPending) {
            $displayClockIn  = $reqIn  ? $toCarbon($reqIn)  : $displayClockIn;
            $displayClockOut = $reqOut ? $toCarbon($reqOut) : $displayClockOut;
        }

        // 休憩：確定値（編集用）と表示用（申請反映）を分離
        $officialBreaks = $attendance->breakTimes?->sortBy('break_start')->values() ?? collect();
        $displayBreaks  = $officialBreaks;
        if ($isPending && !empty($reqBrs)) {
            $displayBreaks = collect($reqBrs)->map(fn($b) => (object)[
                'break_start' => $toCarbon($b['start'] ?? null),
                'break_end'   => $toCarbon($b['end']   ?? null),
            ])->values();
        }

        $displayNote = $isPending ? ($requestRec->reason ?? '') : ($attendance->note ?? '');

        return view('admin.attendance.show', [
            'attendance'      => $attendance,
            'date'            => $date,
            'isPending'       => $isPending,
            'breaks'          => $officialBreaks, // 編集用（確定値）
            'displayBreaks'   => $displayBreaks,  // 閲覧用（申請反映）
            'displayClockIn'  => $displayClockIn,
            'displayClockOut' => $displayClockOut,
            'displayNote'     => $displayNote,
        ]);
    }

    // 勤怠修正
    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        // 基準日（画面の hidden "date" → レコード → 現在）
        $baseDateStr = $request->input('date')
            ?? $attendance->work_date?->toDateString()
            ?? $attendance->clock_in?->toDateString()
            ?? now()->toDateString();

        $date = Carbon::parse($baseDateStr);

        // "HH:MM" を当日の DateTime に変換
        $toDT = fn(?string $hm) => $hm && str_contains($hm, ':')
            ? $date->copy()->setTime(...array_map('intval', explode(':', $hm, 2)) + [0])
            : null;

        $attendance->update([
            'clock_in'  => $toDT($request->input('clock_in')),
            'clock_out' => $toDT($request->input('clock_out')),
            'note'      => $request->input('note'),
        ]);

        // 休憩は全入れ替え（start/end 両方あるものだけ作成）
        $attendance->breakTimes()->delete();
        foreach (collect($request->input('breaks', [])) as $b) {
            $start = $toDT($b['start'] ?? null);
            $end   = $toDT($b['end']   ?? null);
            if ($start && $end) {
                $attendance->breakTimes()->create(['break_start' => $start, 'break_end' => $end]);
            }
        }

        // 戻り先判定
        $contextDate = $request->input('context_date')
            ?? $attendance->work_date?->toDateString()
            ?? $attendance->clock_in?->toDateString()
            ?? $baseDateStr;

        $staffId = $request->input('context_staff') ?? $attendance->user_id;
        $month   = $request->input('context_month') ?? Carbon::parse($contextDate)->format('Y-m');
        $dest    = $request->input('return_to', '');

        [$route, $params] = match (true) {
            in_array($dest, ['list','admin.attendance.index'], true)
                => ['admin.attendance.index', ['date' => $contextDate]],
            in_array($dest, ['staff','admin.staff.show'], true)
                => ['admin.staff.show', ['staff' => $staffId, 'month' => $month]],
            in_array($dest, ['request','admin.request.index'], true)
                => ['admin.request.index', []],
            default
                => ['admin.attendance.show', ['attendance' => $attendance->id]],
        };
        return redirect()->route($route, $params);
    }
}
