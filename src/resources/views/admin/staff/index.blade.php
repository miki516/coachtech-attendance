@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/staff/list.css') }}">
@endsection

@section('content')
    <div class="page-staff">
        <h1 class="page-title">スタッフ一覧</h1>

        <section class="sheet">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>氏名</th>
                        <th>メールアドレス</th>
                        <th>月次勤怠</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $s)
                        <tr>
                            <td>{{ $s->name }}</td>
                            <td class="is-email">{{ $s->email }}</td>
                            <td class="cell-action">
                                <a href="{{ route('admin.staff.show', ['staff' => $s->id]) }}">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">スタッフがいません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
