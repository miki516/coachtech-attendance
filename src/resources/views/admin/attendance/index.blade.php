@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance/list.css') }}">
@endsection

@section('content')
    <div class="page-attendance is-admin">
        <h1 class="page-title">{{ $date->format('Y年n月j日') }}の勤怠</h1>

        <div class="sheet-toolbar" role="navigation" aria-label="日付ナビ">
            <div class="sheet-toolbar-left">
                <a class="toolbar-link" href="{{ route('admin.attendance.index', ['date' => $prevDate]) }}">
                    <img class="icon-img" src="{{ asset('images/icons/arrow_left.svg') }}" alt=""
                        aria-hidden="true">
                    前日</a>
            </div>
            <div class="sheet-toolbar-center">
                <img class="icon-calendar" src="{{ asset('images/icons/calendar.svg') }}" alt="" aria-hidden="true">
                <span>{{ $date->format('Y/m/d') }}</span>
            </div>
            <div class="sheet-toolbar-right">
                <a class="toolbar-link" href="{{ route('admin.attendance.index', ['date' => $nextDate]) }}">翌日
                    <img class="icon-img" src="{{ asset('images/icons/arrow_right.svg') }}" alt=""> </a>
            </div>
        </div>

        <section class="sheet">
            <table class="att-table">
                <thead>
                    <tr>
                        <th class="col-name">名前</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th class="col-action">詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="cell-left">{{ $r['name'] }}</td>
                            <td>{{ $r['clock_in'] }}</td>
                            <td>{{ $r['clock_out'] }}</td>
                            <td>{{ $r['break'] }}</td>
                            <td>{{ $r['total'] }}</td>
                            <td class="cell-action">
                                <a
                                    href="{{ $r['attendance_id']
                                        ? route('admin.attendance.show', [
                                            'attendance' => $r['attendance_id'],
                                            'return' => 'admin.attendance.index',
                                            'date' => $date->toDateString(),
                                        ])
                                        : route('admin.attendance.show.by_date', [
                                            'staff' => $r['user_id'],
                                            'date' => $date->toDateString(),
                                            'return' => 'admin.attendance.index',
                                        ]) }}">詳細</a>

                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">該当する勤怠はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
