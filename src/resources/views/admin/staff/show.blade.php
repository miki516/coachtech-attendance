@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/list.css') }}">
@endsection

@section('content')
    <div class="page-attendance">
        <h1 class="page-title">{{ $staff->name }}さんの勤怠</h1>

        <div class="sheet-toolbar">
            <div class="sheet-toolbar-left">
                <a class="toolbar-link"
                    href="{{ route('admin.staff.show', ['staff' => $staff->id, 'month' => $prevMonth]) }}">
                    <img class="icon-img" src="{{ asset('images/icons/arrow_left.svg') }}" alt="" aria-hidden="true">
                    <span>前月</span>
                </a>
            </div>

            <div class="sheet-toolbar-center">
                <img class="icon-calendar" src="{{ asset('images/icons/calendar.svg') }}" alt="" aria-hidden="true">
                {{ $cursor->format('Y/m') }}
            </div>

            <div class="sheet-toolbar-right">
                @if ($nextDisabled)
                    <span class="toolbar-link link-muted" aria-disabled="true">
                        <span>翌月</span>
                        <img class="icon-img" src="{{ asset('images/icons/arrow_right.svg') }}" alt=""
                            aria-hidden="true">
                    </span>
                @else
                    <a class="toolbar-link"
                        href="{{ route('admin.staff.show', ['staff' => $staff->id, 'month' => $nextMonth]) }}">
                        <span>翌月</span>
                        <img class="icon-img" src="{{ asset('images/icons/arrow_right.svg') }}" alt=""
                            aria-hidden="true">
                    </a>
                @endif
            </div>
        </div>

        <div class="sheet">
            <table class="att-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>{{ $r['day']->isoFormat('MM/DD(ddd)') }}</td>
                            <td>{{ $r['rec']?->clock_in?->format('H:i') ?? '' }}</td>
                            <td>{{ $r['rec']?->clock_out?->format('H:i') ?? '' }}</td>
                            <td>{{ $r['break_str'] ?? '' }}</td>
                            <td>{{ $r['work_str'] ?? '' }}</td>
                            <td class="cell-action">
                                {{-- レコードがある日 --}}
                                @if ($r['rec'])
                                    <a
                                        href="{{ route('admin.attendance.show', [
                                            'attendance' => $r['rec']->id,
                                            'return' => 'admin.staff.show', // 戻り先
                                            'staff' => $staff->id, // 誰の月次か
                                            'month' => $cursor->format('Y-m'), // 表示中の月
                                        ]) }}">詳細</a>
                                @else
                                    {{-- レコードが無い日（by_date で詳細へ） --}}
                                    <a
                                        href="{{ route('admin.attendance.show.by_date', [
                                            'staff' => $staff->id,
                                            'date' => $r['day']->toDateString(),
                                            'return' => 'admin.staff.show',
                                            'month' => $cursor->format('Y-m'),
                                        ]) }}">詳細</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="csv-actions">
            <a class="btn-csv"
                href="{{ route('admin.staff.export.csv', ['staff' => $staff->id, 'month' => $cursor->format('Y-m')]) }}">
                CSV出力
            </a>
        </div>
    </div>
@endsection
