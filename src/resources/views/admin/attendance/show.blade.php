@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/detail.css') }}">
@endsection

@section('content')
    <div class="page-attendance">
        <h1 class="page-title">勤怠詳細</h1>

        {{-- 承認待ちなら読み取り専用にする --}}
        @php
            $readOnly = $isPending ?? false;

            // ラベル
            $breakLabel = fn(int $i) => $i === 0 ? '休憩' : '休憩' . ($i + 1);

            // 編集時に old を優先
            $baseBreaks = ($breaks ?? collect())->values();
            $oldBreaks = collect(old('breaks', []));
            $rowCount = $readOnly ? 0 : max($baseBreaks->count(), $oldBreaks->count());
        @endphp

        <form method="POST" action="{{ route('admin.attendance.update', ['attendance' => $attendance->id]) }}">
            @csrf
            @method('PATCH')

            {{-- 呼び元復帰用の隠し値 --}}
            <input type="hidden" name="return_to" value="{{ old('return_to', request('return')) }}">
            <input type="hidden" name="context_date" value="{{ old('context_date', request('date')) }}">
            <input type="hidden" name="context_staff"
                value="{{ old('context_staff', request('staff', $attendance->user_id)) }}">
            <input type="hidden" name="context_month" value="{{ old('context_month', request('month')) }}">
            <input type="hidden" name="date" value="{{ old('date', $date->toDateString()) }}">

            <div class="detail-card {{ $readOnly ? 'read' : 'edit' }}">
                <table class="kv-table">
                    <tr>
                        <th>名前</th>
                        <td>{{ $attendance?->user?->name ?? '—' }}</td>
                    </tr>

                    <tr>
                        <th>日付</th>
                        <td class="date">
                            <span class="date-left">{{ $date->format('Y') }}年</span>
                            <span class="date-right">{{ $date->locale('ja')->isoFormat('M月D日') }}</span>
                        </td>
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
                                    value="{{ old('clock_in', $attendance?->clock_in?->format('H:i') ?? '') }}">
                                <span class="sep">〜</span>
                                <input class="time-input" type="time" name="clock_out"
                                    value="{{ old('clock_out', $attendance?->clock_out?->format('H:i') ?? '') }}">

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

                    {{-- 休憩（承認待ちはテキスト、編集時は入力欄 + old優先） --}}
                    @if ($readOnly)
                        @php $visible = 0; @endphp

                        {{-- $displayBreaks を優先して使う --}}
                        @foreach ($displayBreaks ?? ($breaks ?? collect()) as $b)
                            @php $empty = is_null($b->break_start) && is_null($b->break_end); @endphp
                            @continue($empty)
                            <tr>
                                <th>{{ $breakLabel($visible) }}</th>
                                <td>
                                    <span class="value-inline">
                                        <span class="time-text">{{ $b->break_start?->format('H:i') ?? '—' }}</span>
                                        <span>〜</span>
                                        <span class="time-text">{{ $b->break_end?->format('H:i') ?? '—' }}</span>
                                    </span>
                                </td>
                            </tr>
                            @php $visible++; @endphp
                        @endforeach

                        @if (($displayBreaks ?? ($breaks ?? collect()))->filter(fn($b) => $b->break_start || $b->break_end)->isEmpty())
                            <tr>
                                <th>休憩</th>
                                <td>—</td>
                            </tr>
                        @endif
                    @else
                        @for ($i = 0; $i < $rowCount; $i++)
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

                        {{-- 追加の空行：エラーが無いときだけ表示 --}}
                        @if (!$errors->any())
                            @php $i = $rowCount; @endphp
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
                    @endif

                    {{-- 備考 --}}
                    <tr>
                        <th>備考</th>
                        <td>
                            @if ($readOnly)
                                {{-- 承認待ちは申請理由を表示 --}}
                                <div class="value-text">{{ $displayNote !== '' ? $displayNote : '—' }}</div>
                            @else
                                {{-- 編集時の初期値 --}}
                                <textarea class="note-input" name="note" rows="3">{{ old('note', $displayNote) }}</textarea>
                                @error('note')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            @endif
                        </td>
                    </tr>

                </table>
            </div>

            {{-- フッタ：承認待ちは注意文のみ（ボタン非表示） --}}
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
