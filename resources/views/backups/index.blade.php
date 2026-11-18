@extends('layouts.app')
@section('title', 'Backups')
@section('crumb', 'Database backup & restore')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Database Backups') }}</h2><p>{{ __('Protect your data with regular backups') }}</p></div>
    <div class="actions">
        <form method="POST" action="{{ route('backups.store') }}">@csrf<button class="btn btn-primary">@include('partials.icon',['name'=>'database']) {{ __('Create Backup Now') }}</button></form>
    </div>
</div>

<div class="alert alert-info">
    @include('partials.icon',['name'=>'info'])
    <div>{{ __('Automatic backups run daily at 2:00 AM (when the scheduler is active). Backups are stored securely on the server and can be downloaded or restored anytime.') }}</div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Backup Files') }}</h3><span class="badge badge-blue" style="margin-inline-start:auto">{{ $backups->total() }} {{ __('total') }}</span></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Filename') }}</th><th>{{ __('Type') }}</th><th>{{ __('Size') }}</th><th>{{ __('Created By') }}</th><th>{{ __('Date') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($backups as $b)
                <tr>
                    <td><div class="flex items-center gap-2"><div class="ico ico-blue" style="position:static;width:32px;height:32px">@include('partials.icon',['name'=>'database'])</div><span class="t-title t-mono">{{ $b->filename }}</span></div></td>
                    <td>@if($b->type=='manual')<span class="badge badge-blue">{{ __('Manual') }}</span>@else<span class="badge badge-gray">{{ __('Scheduled') }}</span>@endif</td>
                    <td class="t-mono">{{ $b->readable_size }}</td>
                    <td>{{ $b->creator->name ?? __('System') }}</td>
                    <td>{{ $b->created_at->translatedFormat('d M Y, h:i A') }}</td>
                    <td>
                        <div class="flex gap-1" style="justify-content:flex-end">
                            <a href="{{ route('backups.download',$b) }}" class="icon-btn" title="{{ __('Download') }}">@include('partials.icon',['name'=>'download'])</a>
                            <form method="POST" action="{{ route('backups.restore',$b) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Restore this backup? This will OVERWRITE all current data with the backup contents.') }}')">@csrf<button class="icon-btn" title="{{ __('Restore') }}" style="color:var(--amber)">@include('partials.icon',['name'=>'refresh'])</button></form>
                            <form method="POST" action="{{ route('backups.destroy',$b) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete this backup file?') }}')">@csrf @method('DELETE')<button class="icon-btn danger" title="{{ __('Delete') }}">@include('partials.icon',['name'=>'trash'])</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">@include('partials.icon',['name'=>'database'])<h4>{{ __('No backups yet') }}</h4><p>{{ __('Create your first backup to keep your data safe.') }}</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $backups->links('partials.pagination') }}</div>
</div>
@endsection
