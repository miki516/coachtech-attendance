@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/requests/list.css') }}">
@endsection

@section('content')
    <div class="page-requests">
        <h1 class="page-title">申請一覧</h1>

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
                    @forelse ($pending as $r)
                        <tr>
                            <td>承認待ち</td>
                            <td>{{ $r->user?->name }}</td>
                            <td class="is-date">{{ $r->display_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $r->reason }}</td>
                            <td class="is-date">{{ $r->created_at->format('Y/m/d') }}</td>
                            <td class="cell-action">
                                <a href="{{ route('admin.request.show', $r->id) }}">詳細</a>
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
                    @forelse ($approved as $r)
                        <tr>
                            <td>承認済み</td>
                            <td>{{ $r->user?->name }}</td>
                            <td class="is-date">{{ $r->display_date?->format('Y/m/d') ?? '—' }}</td>
                            <td>{{ $r->reason }}</td>
                            <td class="is-date">{{ $r->created_at?->format('Y/m/d') }}</td>
                            <td class="cell-action">
                                <a href="{{ route('admin.request.show', $r->id) }}">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">承認済みの申請はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        // タブ切り替え（ARIA）
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
