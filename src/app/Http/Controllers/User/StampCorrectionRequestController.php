<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStampCorrectionRequest;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class StampCorrectionRequestController extends Controller
{
    // 修正申請を保存
    public function store(StoreStampCorrectionRequest $request)
    {
        $validated = $request->validated();
        $user = Auth::user();

        // 勤怠データ（あれば取得）
        $attendance = null;
        if (!empty($validated['attendance_id'])) {
            $attendance = Attendance::where('user_id', $user->id)
            ->where('id', $validated['attendance_id'])
            ->first();
        }

        // 時刻→DateTime文字列
        $toDateTime = function (?string $time, Carbon $baseDate) {
            if (!$time) return null;
            [$h, $m] = explode(':', $time);
            return $baseDate->copy()->setTime((int)$h, (int)$m)->toDateTimeString();
        };

        $baseDate = Carbon::parse($validated['date']);

        // 休憩の申請値をJSON用に整形
        $requestedBreaks = [];
        foreach ($validated['breaks'] ?? [] as $b) {
            $requestedBreaks[] = [
                'start' => $toDateTime($b['start'] ?? null, $baseDate),
                'end'   => $toDateTime($b['end']   ?? null, $baseDate),
            ];
        }

        // 修正申請を登録
        StampCorrectionRequest::create([
            'user_id'             => $user->id,
            'attendance_id'       => $attendance?->id,
            'target_date'         => $baseDate->toDateString(),
            'requested_clock_in'  => $toDateTime($validated['clock_in'] ?? null, $baseDate),
            'requested_clock_out' => $toDateTime($validated['clock_out'] ?? null, $baseDate),
            'requested_breaks'    => $requestedBreaks,
            'reason'              => $validated['note'],
            'status'              => 'pending',
        ]);

        return redirect()->route('request.list');
    }

    // 申請一覧
    public function index(Request $request)
    {
        $user = Auth::user();

        // 表示用変換（対象日付の整形）
        $decorate = function ($r) {
            $source = $r->target_date ?: ($r->attendance?->clock_in);
            $date   = $source ? Carbon::parse($source) : null;
            $r->display_date = $date;
            $r->link_date    = $date?->format('Y-m-d');
            return $r;
        };

        // ベースクエリ（user も preload）
        $base = StampCorrectionRequest::with(['attendance','user'])
            ->where('user_id', $user->id)
            ->latest('created_at');

        // 状態ごとに DB で絞り込み
        $pending  = (clone $base)->where('status', 'pending')->get()->map($decorate);
        $approved = (clone $base)->where('status', 'approved')->get()->map($decorate);
        $rejected = (clone $base)->where('status', 'rejected')->get()->map($decorate);

        return view('user.stamp_correction_request.index', compact('pending','approved','rejected'));
    }

}
