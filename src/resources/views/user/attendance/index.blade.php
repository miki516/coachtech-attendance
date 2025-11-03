@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/list.css') }}">
@endsection

@section('content')
    <div class="page-attendance">
        <h1 class="page-title">勤怠一覧</h1>

        <div class="sheet-toolbar">
            <div class="sheet-toolbar-left">
                <a class="toolbar-link" href="{{ route('user.attendance.index', ['month' => $prevMonth]) }}">
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
                    <span class="toolbar-link" aria-disabled="true">
                        <span>翌月</span>
                        <img class="icon-img" src="{{ asset('images/icons/arrow_right.svg') }}" alt=""
                            aria-hidden="true">
                    </span>
                @else
                    <a class="toolbar-link" href="{{ route('user.attendance.index', ['month' => $nextMonth]) }}">
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
                                @if ($r['rec'])
                                    <a href="{{ route('user.attendance.show', ['attendance' => $r['rec']->id]) }}">詳細</a>
                                @else
                                    <a
                                        href="{{ route('user.attendance.show.by_date', ['date' => $r['day']->toDateString()]) }}">詳細</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
