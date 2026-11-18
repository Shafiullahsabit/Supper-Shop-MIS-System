@extends('layouts.app')
@section('title', 'Cash Register')
@section('crumb', 'Open and close your till')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Cash Register') }}</h2><p>{{ __('Manage your cash drawer session') }}</p></div>
</div>

@if($current)
    <div class="card card-pad mb-2" style="border-inline-start:4px solid var(--green)">
        <div class="flex items-center gap-3" style="flex-wrap:wrap">
            <div class="ico ico-green" style="position:static;width:52px;height:52px">@include('partials.icon',['name'=>'wallet'])</div>
            <div>
                <div class="flex items-center gap-2"><h3>{{ __('Register is open') }}</h3><span class="badge badge-green"><span class="dot"></span> {{ __('Live') }}</span></div>
                <div class="text-muted">{{ __('Opened') }} {{ $current->opened_at->translatedFormat('d M Y, h:i A') }} · {{ $current->opened_at->diffForHumans() }}</div>
            </div>
            <button class="btn btn-danger" style="margin-inline-start:auto" onclick="SS.openModal('closeModal')">@include('partials.icon',['name'=>'logout']) {{ __('Close Register & Z-Report') }}</button>
        </div>
        <div class="grid grid-4 mt-3">
            <div><div class="text-muted fs-sm">{{ __('Opening Float') }}</div><div class="fs-lg fw-700 t-mono">{{ money($current->opening_float) }}</div></div>
            <div><div class="text-muted fs-sm">{{ __('Cash Sales') }}</div><div class="fs-lg fw-700 t-mono text-green">{{ money($current->cash_sales) }}</div></div>
            <div><div class="text-muted fs-sm">{{ __('Card + Mobile') }}</div><div class="fs-lg fw-700 t-mono">{{ money($current->card_sales + $current->other_sales) }}</div></div>
            <div><div class="text-muted fs-sm">{{ __('Expected in Drawer') }}</div><div class="fs-lg fw-700 t-mono text-blue">{{ money($current->expected_cash) }}</div></div>
        </div>
    </div>
@else
    <div class="card card-pad mb-2" style="border-inline-start:4px solid var(--amber)">
        <div class="flex items-center gap-3" style="flex-wrap:wrap">
            <div class="ico ico-amber" style="position:static;width:52px;height:52px">@include('partials.icon',['name'=>'wallet'])</div>
            <div><h3>{{ __('Register is closed') }}</h3><div class="text-muted">{{ __('Open the register with a starting cash float to begin your shift.') }}</div></div>
            <button class="btn btn-primary" style="margin-inline-start:auto" onclick="SS.openModal('openModal')">@include('partials.icon',['name'=>'plus']) {{ __('Open Register') }}</button>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-head"><h3>{{ __('Session History') }}</h3></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Cashier') }}</th>@if(auth()->user()->hasRole('Super Admin'))<th>{{ __('Branch') }}</th>@endif<th>{{ __('Opened') }}</th><th>{{ __('Closed') }}</th><th class="t-right">{{ __('Cash Sales') }}</th><th class="t-right">{{ __('Expected') }}</th><th class="t-right">{{ __('Difference') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($sessions as $s)
                <tr onclick="location='{{ route('register.show',$s) }}'" style="cursor:pointer">
                    <td class="t-title">{{ $s->user->name ?? '—' }}</td>
                    @if(auth()->user()->hasRole('Super Admin'))<td>{{ $s->branch?->branch_number }} · {{ $s->branch?->name }}</td>@endif
                    <td>{{ $s->opened_at?->translatedFormat('d M, h:i A') }}</td>
                    <td>{{ $s->closed_at?->translatedFormat('d M, h:i A') ?? '—' }}</td>
                    <td class="t-right t-mono">{{ money($s->cash_sales) }}</td>
                    <td class="t-right t-mono">{{ money($s->expected_cash) }}</td>
                    <td class="t-right t-mono fw-700 {{ $s->difference < 0 ? 'text-red' : ($s->difference > 0 ? 'text-green' : '') }}">{{ $s->status=='closed' ? money($s->difference) : '—' }}</td>
                    <td>@if($s->status=='open')<span class="badge badge-green">{{ __('Open') }}</span>@else<span class="badge badge-gray">{{ __('Closed') }}</span>@endif</td>
                    <td onclick="event.stopPropagation()"><a href="{{ route('register.show',$s) }}" class="icon-btn">@include('partials.icon',['name'=>'eye'])</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->hasRole('Super Admin') ? 9 : 8 }}"><div class="empty">@include('partials.icon',['name'=>'wallet'])<h4>{{ __('No register sessions yet') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $sessions->links('partials.pagination') }}</div>
</div>

{{-- Open modal --}}
<div class="modal-overlay" id="openModal" style="display:none">
    <form class="modal" method="POST" action="{{ route('register.open') }}">
        @csrf
        <div class="modal-head"><h3>{{ __('Open Register') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('openModal')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="field" style="margin:0"><label>{{ __('Opening cash float') }}</label>
                <input class="input input-lg t-mono" type="number" step="0.01" min="0" name="opening_float" value="0.00" required autofocus>
                <div class="hint">{{ __('The amount of cash currently in the drawer to start the shift.') }}</div>
            </div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('openModal')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Open Register') }}</button></div>
    </form>
</div>

{{-- Close modal --}}
@if($current)
<div class="modal-overlay" id="closeModal" style="display:none">
    <form class="modal" method="POST" action="{{ route('register.close',$current) }}">
        @csrf
        <div class="modal-head"><h3>{{ __('Close Register') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('closeModal')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="alert alert-info" style="margin-bottom:16px">@include('partials.icon',['name'=>'info']) {{ __('Expected cash in drawer:') }} <b>{{ money($current->expected_cash) }}</b></div>
            <div class="field"><label>{{ __('Counted cash in drawer') }}</label><input class="input input-lg t-mono" type="number" step="0.01" min="0" name="counted_cash" required autofocus></div>
            <div class="field" style="margin:0"><label>{{ __('Note (optional)') }}</label><textarea class="input" name="note" placeholder="{{ __('Any discrepancies…') }}"></textarea></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('closeModal')">{{ __('Cancel') }}</button><button class="btn btn-danger">{{ __('Close & Generate Z-Report') }}</button></div>
    </form>
</div>
@endif
@endsection
