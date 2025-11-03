@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/detail.css') }}">
@endsection

@section('content')
    <div class="page-attendance">
        <h1 class="page-title">勤怠詳細</h1>

        <form action="{{ route('user.request.store') }}" method="POST">
            @csrf
            @php
                $readOnly = $isPending;

                // 休憩ラベル（0始まりの index）
                $breakLabel = fn(int $i) => $i === 0 ? '休憩' : '休憩' . ($i + 1);
            @endphp

            {{-- 対象特定（work_date 優先） --}}
            <input type="hidden" name="date" value="{{ ($attendance->work_date ?? $date)->toDateString() }}">
            <input type="hidden" name="attendance_id" value="{{ $attendance->id }}">

            <div class="detail-card {{ $readOnly ? 'read' : 'edit' }}">
                <table class="kv-table">
                    <tr>
                        <th>名前</th>
                        <td>{{ Auth::user()->name }}</td>
                    </tr>

                    <tr>
                        <th>日付</th>
                        <td>{{ $date->format('Y年 n月j日') }}</td>
                    </tr>

                    {{-- 出勤・退勤 --}}
                    <tr>
                        <th>出勤・退勤</th>
                        <td class="{{ $readOnly ? '' : 'td-center' }}">
                            @if ($readOnly)
                                <span class="value-inline">
                                    <span class="time-text">{{ $displayClockIn?->format('H:i') ?? '—' }}</span>
                                    <span>〜</span>
                                    <span class="time-text">{{ $displayClockOut?->format('H:i') ?? '—' }}</span>
                                </span>
                            @else
                                <input class="time-input" type="time" name="clock_in"
                                    value="{{ old('clock_in', $displayClockIn?->format('H:i') ?? '') }}">
                                <span class="sep">〜</span>
                                <input class="time-input" type="time" name="clock_out"
                                    value="{{ old('clock_out', $displayClockOut?->format('H:i') ?? '') }}">

                                @php
                                    $clockErrors = collect([$errors->first('clock_in'), $errors->first('clock_out')])
                                        ->filter()
                                        ->unique();
                                @endphp
                                @foreach ($clockErrors as $msg)
                                    <div class="form-error">{{ $msg }}</div>
                                @endforeach
                            @endif
                        </td>
                    </tr>

                    {{-- 休憩（既存分） --}}
                    @php
                        // ラベル
                        $breakLabel = fn(int $i) => $i === 0 ? '休憩' : '休憩' . ($i + 1);

                        // 表示用の元データ（DB/申請値）
                        $baseBreaks = $displayBreaks->values();

                        // 直前入力（old）があればそれを優先するため、描画行数は old と base の多い方
                        $oldBreaks = collect(old('breaks', []));
                        $rows = max($baseBreaks->count(), $oldBreaks->count());
                    @endphp

                    {{-- 編集モード --}}
                    @unless ($readOnly)
                        @for ($i = 0; $i < $rows; $i++)
                            @php
                                $base = $baseBreaks[$i] ?? null;
                                $startVal = old("breaks.$i.start", $base?->break_start?->format('H:i') ?? '');
                                $endVal = old("breaks.$i.end", $base?->break_end?->format('H:i') ?? '');
                            @endphp
                            <tr>
                                <th>{{ $breakLabel($i) }}</th>
                                <td class="td-center">
                                    <input class="time-input" type="time" name="breaks[{{ $i }}][start]"
                                        value="{{ $startVal }}">
                                    <span class="sep">〜</span>
                                    <input class="time-input" type="time" name="breaks[{{ $i }}][end]"
                                        value="{{ $endVal }}">
                                    @error("breaks.$i.start")
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                    @error("breaks.$i.end")
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>
                        @endfor

                        {{-- 追加の空行 --}}
                        @if (!$errors->any())
                            @php $i = $rows; @endphp
                            <tr>
                                <th>{{ $breakLabel($i) }}</th>
                                <td class="td-center">
                                    <input class="time-input" type="time" name="breaks[{{ $i }}][start]"
                                        value="">
                                    <span class="sep">〜</span>
                                    <input class="time-input" type="time" name="breaks[{{ $i }}][end]"
                                        value="">
                                </td>
                            </tr>
                        @endif
                    @endunless

                    {{-- 読み取り専用モード（承認待ち） --}}
                    @isset($readOnly)
                        @if ($readOnly)
                            @php $visibleIdx = 0; @endphp
                            @foreach ($displayBreaks as $b)
                                @php $isEmpty = is_null($b->break_start) && is_null($b->break_end); @endphp
                                @continue($isEmpty)
                                <tr>
                                    <th>{{ $breakLabel($visibleIdx) }}</th>
                                    <td>
                                        <span class="value-inline">
                                            <span class="break-text">{{ $b->break_start?->format('H:i') ?? '—' }}</span>
                                            <span>〜</span>
                                            <span class="break-text">{{ $b->break_end?->format('H:i') ?? '—' }}</span>
                                        </span>
                                    </td>
                                </tr>
                                @php $visibleIdx++; @endphp
                            @endforeach
                        @endif
                    @endisset

                    <tr>
                        <th>備考</th>
                        <td>
                            @if ($readOnly)
                                <div class="value-text">{{ $displayNote !== '' ? $displayNote : '—' }}</div>
                            @else
                                <textarea class="note-input" name="note" rows="3">{{ old('note', $displayNote) }}</textarea>
                                @error('note')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

            {{-- 承認待ちのときはボタン非表示 --}}
            @if ($readOnly)
                <p class="note-danger" role="status" aria-live="polite">※承認待ちのため修正はできません。</p>
            @else
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">修正</button>
                </div>
            @endif
        </form>
    </div>
@endsection
