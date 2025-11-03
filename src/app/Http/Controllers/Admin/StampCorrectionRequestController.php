<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StampCorrectionRequestController extends Controller
{
    // 承認一覧画面
    public function index(Request $request)
    {
        $allRequests = StampCorrectionRequest::with(['attendance', 'user'])
            ->latest('created_at')
            ->get()
            ->map(function ($r) {
                $source = $r->target_date ?: ($r->attendance?->clock_in);
                $r->setAttribute('display_date', $source ? Carbon::parse($source) : null);
                return $r;
            });

        $pending  = $allRequests->where('status', StampCorrectionRequest::STATUS_PENDING);
        $approved = $allRequests->where('status', StampCorrectionRequest::STATUS_APPROVED);

        return view('admin.request.index', compact('pending', 'approved'));
    }

    // 詳細表示
    public function show(Request $request)
    {
        $requestId  = (int) $request->route('attendance_correct_request_id');
        $requestRec = StampCorrectionRequest::with(['attendance', 'user'])->findOrFail($requestId);

        $requestRec->setAttribute(
            'clock_in_time',
            $requestRec->requested_clock_in ? Carbon::parse($requestRec->requested_clock_in, 'Asia/Tokyo') : null
        );
        $requestRec->setAttribute(
            'clock_out_time',
            $requestRec->requested_clock_out ? Carbon::parse($requestRec->requested_clock_out, 'Asia/Tokyo') : null
        );

        $requestRec->setAttribute(
            'break_times',
            collect($requestRec->requested_breaks ?? [])->map(function ($b) {
                return [
                    'start' => !empty($b['start']) ? Carbon::parse($b['start'], 'Asia/Tokyo') : null,
                    'end'   => !empty($b['end'])   ? Carbon::parse($b['end'],   'Asia/Tokyo') : null,
                ];
            })
        );

        $date = $requestRec->target_date
            ? Carbon::parse($requestRec->target_date, 'Asia/Tokyo')->startOfDay()
            : ($requestRec->attendance?->clock_in?->copy()->startOfDay() ?? now('Asia/Tokyo')->startOfDay());

        return view('admin.request.show', compact('requestRec', 'date'));
    }

    // 承認処理
    public function approve(Request $request)
    {
        $requestId = (int) $request->route('attendance_correct_request_id');
        $rec = StampCorrectionRequest::with(['attendance', 'user'])->findOrFail($requestId);

        DB::transaction(function () use ($rec) {
            // 1) 対象日の決定（target_date → requested_* → 既存attendance → 今日）
            $workDate = null;

            if (!empty($rec->target_date)) {
                $workDate = Carbon::parse($rec->target_date, 'Asia/Tokyo')->toDateString();
            } elseif (!empty($rec->requested_clock_in)) {
                $workDate = Carbon::parse($rec->requested_clock_in, 'Asia/Tokyo')->toDateString();
            } elseif (!empty($rec->requested_clock_out)) {
                $workDate = Carbon::parse($rec->requested_clock_out, 'Asia/Tokyo')->toDateString();
            } elseif ($rec->attendance?->work_date) {
                $workDate = $rec->attendance->work_date->toDateString();
            } else {
                $workDate = now('Asia/Tokyo')->toDateString();
            }

            // 2) Attendance（既存 or 作成）
            $attendance = $rec->attendance
                ?: Attendance::firstOrCreate(
                    ['user_id' => $rec->user_id, 'work_date' => $workDate],
                    ['clock_in' => null, 'clock_out' => null]
                );

            // 3) 出勤・退勤の反映（申請があるものだけ上書き）
            if (!empty($rec->requested_clock_in)) {
                $attendance->clock_in = Carbon::parse($rec->requested_clock_in, 'Asia/Tokyo');
            }
            if (!empty($rec->requested_clock_out)) {
                $attendance->clock_out = Carbon::parse($rec->requested_clock_out, 'Asia/Tokyo');
            }
            if (filled($rec->reason)) {
                $attendance->note = $rec->reason;
            }
            $attendance->save();

            if (!is_null($rec->reason)) {
                $attendance->note = trim($rec->reason) === '' ? null : $rec->reason;
            }

            // 4) 休憩を置き換え（start/end 両方あるもののみ）
            $attendance->breakTimes()->delete();
            foreach (($rec->requested_breaks ?? []) as $b) {
                if (!empty($b['start']) && !empty($b['end'])) {
                    $attendance->breakTimes()->create([
                        'break_start' => Carbon::parse($b['start'], 'Asia/Tokyo'),
                        'break_end'   => Carbon::parse($b['end'],   'Asia/Tokyo'),
                    ]);
                }
            }

            // 5) 申請を承認済みに更新（管理者ガードで承認者ID）
            $rec->update([
                'status'      => StampCorrectionRequest::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now('Asia/Tokyo'),
            ]);
        });

        return redirect()->route('admin.request.index')->with('status', '申請を承認しました');
    }
}
