@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/punch.css') }}">
@endsection

@section('content')
    <main class="clock">
        <div class="clock-card">
            <span class="status-badge">{{ $status ?? '勤務外' }}</span>

            <div id="now-date" class="now-date">
                {{ $now->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}
            </div>

            <div id="now-time" class="now-time">
                {{ $now->format('H:i') }}
            </div>

            <span id="now-start" data-start="{{ $now->toIso8601String() }}" hidden></span>

            <div class="clock-actions">
                {{-- 退勤済みならメッセージだけ表示 --}}
                @if ($status === '退勤済')
                    <p class="flash-success">お疲れ様でした。</p>
                @else
                    {{-- 出勤ボタン（勤務外のとき） --}}
                    @if ($canClockIn)
                        <form method="POST" action="{{ route('user.attendance.clockin') }}">
                            @csrf
                            <button type="submit" class="btn-clock btn-primary">出勤</button>
                        </form>
                    @endif

                    {{-- 退勤ボタン（出勤中 かつ 休憩中ではないとき） --}}
                    @if ($canClockOut && !$canBreakOut)
                        <form method="POST" action="{{ route('user.attendance.clockout', $open->id) }}">
                            @csrf
                            <button type="submit" class="btn-clock btn-primary">退勤</button>
                        </form>
                    @endif

                    {{-- 休憩入（出勤中で休憩に入っていないとき） --}}
                    @if ($canBreakIn)
                        <form method="POST" action="{{ route('user.attendance.break.in', $open->id) }}">
                            @csrf
                            <button type="submit" class="btn-clock btn-break">休憩入</button>
                        </form>
                    @endif

                    {{-- 休憩戻（休憩中のとき） --}}
                    @if ($canBreakOut)
                        <form method="POST" action="{{ route('user.attendance.break.out', $open->id) }}">
                            @csrf
                            <button type="submit" class="btn-clock btn-break">休憩戻</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const startEl = document.getElementById('now-start');
            const dateEl = document.getElementById('now-date');
            const timeEl = document.getElementById('now-time');
            if (!startEl || !timeEl) return;

            const serverStart = new Date(startEl.dataset.start).getTime();
            const offset = Date.now() - serverStart;

            const wdays = ['日', '月', '火', '水', '木', '金', '土'];
            const pad2 = n => String(n).padStart(2, '0');

            function render() {
                const t = new Date(Date.now() - offset);
                const y = t.getFullYear();
                const m = t.getMonth() + 1;
                const d = t.getDate();
                const w = wdays[t.getDay()];
                const hh = pad2(t.getHours());
                const mm = pad2(t.getMinutes());
                dateEl.textContent = `${y}年${m}月${d}日(${w})`;
                timeEl.textContent = `${hh}:${mm}`;
            }
            render();
            setInterval(render, 1000);
        })();
    </script>
@endpush
