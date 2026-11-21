@extends('layouts.app')
@section('title', 'Stock Transfers')
@section('crumb', 'Move stock between branches')

@section('content')
<div class="page-head">
    <div class="titles">
        <h2>{{ __('Stock Transfers') }}</h2>
        <p>{{ __('Request, approve, dispatch, and receive stock between branches.') }}</p>
    </div>
    <div class="actions">
        <a href="{{ route('transfers.create') }}" class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) {{ __('New Transfer Request') }}</a>
    </div>
</div>

<div class="grid grid-3 mb-2">
    <div class="card card-pad stat">
        <div class="ico ico-amber">@include('partials.icon', ['name' => 'clock'])</div>
        <div class="label">{{ __('Awaiting Approval') }}</div>
        <div class="value">{{ number_format($stats['pending']) }}</div>
    </div>
    <div class="card card-pad stat">
        <div class="ico ico-blue">@include('partials.icon', ['name' => 'truck'])</div>
        <div class="label">{{ __('In Transit') }}</div>
        <div class="value">{{ number_format($stats['in_transit']) }}</div>
    </div>
    <div class="card card-pad stat">
        <div class="ico ico-green">@include('partials.icon', ['name' => 'check-circle'])</div>
        <div class="label">{{ __('Completed Transfers') }}</div>
        <div class="value">{{ number_format($stats['completed']) }}</div>
    </div>
</div>

<form method="GET" class="toolbar">
    <div class="search-box">
        @include('partials.icon', ['name' => 'search'])
        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search transfer reference…') }}">
    </div>
    <select name="status" class="select" style="width:auto">
        <option value="">{{ __('All statuses') }}</option>
        @foreach($statuses as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ __($status) }}</option>
        @endforeach
    </select>
    <button class="btn btn-light">@include('partials.icon', ['name' => 'filter']) {{ __('Filter') }}</button>
    @if(request()->hasAny(['q', 'status']))
        <a class="btn btn-light" href="{{ route('transfers.index') }}">{{ __('Clear') }}</a>
    @endif
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ __('From branch') }}</th>
                    <th>{{ __('To branch') }}</th>
                    <th>{{ __('Items') }}</th>
                    <th>{{ __('Requested by') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td class="t-mono fw-700">{{ $transfer->reference }}</td>
                        <td>{{ $transfer->sourceBranch->branch_number }} · {{ $transfer->sourceBranch->name }}</td>
                        <td>{{ $transfer->destinationBranch->branch_number }} · {{ $transfer->destinationBranch->name }}</td>
                        <td>{{ $transfer->items_count }}</td>
                        <td>{{ $transfer->creator?->name ?? '—' }}</td>
                        <td>{{ $transfer->created_at->translatedFormat('d M Y, h:i A') }}</td>
                        <td>
                            @php
                                $statusClass = match ($transfer->status) {
                                    'pending_approval' => 'badge-amber',
                                    'approved', 'in_transit', 'partially_received' => 'badge-blue',
                                    'received' => 'badge-green',
                                    'received_with_discrepancy', 'rejected' => 'badge-red',
                                    default => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ __($transfer->status) }}</span>
                        </td>
                        <td class="t-right"><a class="btn btn-light btn-sm" href="{{ route('transfers.show', $transfer) }}">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">@include('partials.icon', ['name' => 'truck'])<h4>{{ __('No stock transfers found') }}</h4><p>{{ __('Create a transfer request to move stock between branches.') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $transfers->links('partials.pagination') }}</div>
</div>
@endsection
