@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/detail.css') }}">
@endsection

@section('content')
    <div class="page-attendance">
        <h1 class="page-title">勤怠詳細</h1>

        @php
            // ラベルと休憩配列の整形（空行は除外）
            $breakLabel = fn(int $i) => $i === 0 ? '休憩' : '休憩' . ($i + 1);
            $validBreaks = collect($requestRec->break_times ?? [])
                ->filter(fn($b) => ($b['start'] ?? null) || ($b['end'] ?? null))
                ->values();
        @endphp

        {{-- 常に read モード --}}
        <div class="detail-card read">
            <table class="kv-table">
                <tr>
                    <th>名前</th>
                    <td>{{ $requestRec->user?->name ?? '—' }}</td>
                </tr>

                <tr>
                    <th>日付</th>
                    <td class="date">
                        <span class="date-left">{{ $date->format('Y') }}年</span>
                        <span class="date-right">{{ $date->locale('ja')->isoFormat('M月D日') }}</span>
                    </td>
                </tr>

                {{-- 出勤・退勤：テキスト表示 --}}
                <tr>
                    <th>出勤・退勤</th>
                    <td>
                        <span class="value-inline">
                            <span class="time-text">{{ $requestRec->clock_in_time?->format('H:i') ?? '—' }}</span>
                            <span>〜</span>
                            <span class="time-text">{{ $requestRec->clock_out_time?->format('H:i') ?? '—' }}</span>
                        </span>
                    </td>
                </tr>

                {{-- 休憩：テキスト表示 --}}
                @forelse ($validBreaks as $i => $b)
                    <tr>
                        <th>{{ $breakLabel($i) }}</th>
                        <td>
                            <span class="value-inline">
                                <span class="time-text">{{ $b['start']?->format('H:i') ?? '—' }}</span>
                                <span>〜</span>
                                <span class="time-text">{{ $b['end']?->format('H:i') ?? '—' }}</span>
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <th>休憩</th>
                        <td>—</td>
                    </tr>
                @endforelse

                {{-- 備考：テキスト表示 --}}
                <tr>
                    <th>備考</th>
                    <td>
                        <div class="value-text">{{ ($requestRec->reason ?? '') !== '' ? $requestRec->reason : '—' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- 承認／承認済みボタン --}}
        <div class="form-actions">
            @if ($requestRec->status === 'pending')
                <form method="POST" action="{{ route('admin.request.approve', $requestRec->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">承認</button>
                </form>
            @else
                <button type="button" class="btn btn-muted" disabled>承認済み</button>
            @endif
        </div>
    </div>
@endsection
