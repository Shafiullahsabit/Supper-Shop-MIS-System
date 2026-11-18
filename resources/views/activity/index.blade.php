@extends('layouts.app')
@section('title', 'Activity Log')
@section('crumb', 'Audit trail of system events')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Activity Log') }}</h2><p>{{ $logs->total() }} {{ __('recorded events') }}</p></div>
</div>

<form method="GET" class="toolbar">
    <div class="search-box">@include('partials.icon',['name'=>'search'])<input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search descriptions…') }}"></div>
    <select name="action" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">{{ __('All actions') }}</option>
        @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')==$a)>{{ __(ucfirst($a)) }}</option>@endforeach
    </select>
    <button class="btn btn-light">{{ __('Filter') }}</button>
    @if(request()->hasAny(['q','action']))<a href="{{ route('activity.index') }}" class="btn btn-light">{{ __('Reset') }}</a>@endif
</form>

@php
    $colors = ['login'=>'green','logout'=>'gray','created'=>'blue','updated'=>'amber','deleted'=>'red','sale'=>'green','refund'=>'red','backup'=>'violet','restore'=>'amber','settings'=>'blue','register'=>'blue','adjusted'=>'amber','drawer'=>'gray'];
@endphp
<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th>@if(auth()->user()->hasRole('Super Admin'))<th>{{ __('Branch') }}</th>@endif<th>{{ __('Action') }}</th><th>{{ __('Description') }}</th><th>{{ __('IP') }}</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at->translatedFormat('d M Y, h:i A') }}<div class="t-sub">{{ $log->created_at->diffForHumans() }}</div></td>
                    <td class="t-title">{{ $log->user->name ?? __('System') }}</td>
                    @if(auth()->user()->hasRole('Super Admin'))<td>{{ $log->branch?->branch_number }} · {{ $log->branch?->name }}</td>@endif
                    <td><span class="badge badge-{{ $colors[$log->action] ?? 'gray' }}">{{ __(ucfirst($log->action)) }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td class="t-mono t-sub">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->hasRole('Super Admin') ? 6 : 5 }}"><div class="empty">@include('partials.icon',['name'=>'activity'])<h4>{{ __('No activity recorded') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $logs->links('partials.pagination') }}</div>
</div>
@endsection
