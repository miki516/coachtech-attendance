@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/requests/list.css') }}">
@endsection

@section('content')
    <div class="page-requests">
        <h1 class="page-title">申請一覧</h1>

        {{-- タブ --}}
        <div class="tabs" role="tablist">
            <button class="tab" role="tab" aria-selected="true" data-target="pending-panel">承認待ち</button>
            <button class="tab" role="tab" aria-selected="false" data-target="approved-panel">承認済み</button>
        </div>

        {{-- 承認待ち --}}
        <section id="pending-panel" class="sheet" role="tabpanel">
            <table class="req-table">
                <thead>
                    <tr>
                        <th>状態</th>
                        <th>名前</th>
                        <th>対象日時</th>
                        <th>申請理由</th>
                        <th>申請日時</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $req)
                        <tr>
                            <td>承認待ち</td>
                            <td>{{ $req->user->name }}</td>
                            <td class="is-date">{{ $req->display_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $req->reason }}</td>
                            <td class="is-date">{{ $req->created_at->format('Y/m/d') }}</td>
                            <td class="cell-action">
                                @if ($req->attendance_id)
                                    <a
                                        href="{{ route('user.attendance.show', ['attendance' => $req->attendance_id]) }}">詳細</a>
                                @elseif ($req->display_date)
                                    <a
                                        href="{{ route('user.attendance.show.by_date', ['date' => $req->display_date->toDateString()]) }}">詳細</a>
                                @else
                                    <span style="opacity:.6;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">承認待ちはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- 承認済み --}}
        <section id="approved-panel" class="sheet" role="tabpanel" hidden>
            <table class="req-table">
                <thead>
                    <tr>
                        <th>状態</th>
                        <th>名前</th>
                        <th>対象日時</th>
                        <th>申請理由</th>
                        <th>申請日時</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($approved as $req)
                        <tr>
                            <td>承認済み</td>
                            <td>{{ $req->user->name }}</td>
                            <td class="is-date">{{ $req->display_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $req->reason }}</td>
                            <td class="is-date">{{ $req->created_at->format('Y/m/d') }}</td>
                            <td class="cell-action">
                                @if ($req->attendance_id)
                                    <a
                                        href="{{ route('user.attendance.show', ['attendance' => $req->attendance_id]) }}">詳細</a>
                                @elseif ($req->display_date)
                                    <a
                                        href="{{ route('user.attendance.show.by_date', ['date' => $req->display_date->toDateString()]) }}">詳細</a>
                                @else
                                    <span style="opacity:.6;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">承認済みはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        // タブ切り替え（ARIA対応）
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.setAttribute('aria-selected', 'false'));
                    document.querySelectorAll('[role="tabpanel"]').forEach(p => p.hidden = true);

                    tab.setAttribute('aria-selected', 'true');
                    document.getElementById(tab.dataset.target).hidden = false;
                });
            });
        });
    </script>
@endpush
