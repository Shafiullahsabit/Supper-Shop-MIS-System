@extends('layouts.app')
@section('title', 'Branches')
@section('crumb', 'Manage store branches')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Branches') }}</h2><p>{{ __('Manage store locations and branch numbers') }}</p></div>
</div>

<div class="card card-pad mb-2">
    <h3 style="margin-bottom:16px">{{ __('Add Branch') }}</h3>
    <form method="POST" action="{{ route('branches.store') }}">
        @csrf
        <div class="form-row">
            <div class="field"><label>{{ __('Exact branch name *') }}</label><input class="input" name="name" value="{{ old('name') }}" required maxlength="150"></div>
            <div class="field"><label>{{ __('Unique branch number *') }}</label><input class="input t-mono" name="branch_number" value="{{ old('branch_number') }}" inputmode="numeric" pattern="[0-9]+" required maxlength="32"></div>
        </div>
        <div class="form-row">
            <div class="field"><label>{{ __('Address') }}</label><input class="input" name="address" value="{{ old('address') }}"></div>
            <div class="field"><label>{{ __('Phone') }}</label><input class="input" name="phone" value="{{ old('phone') }}"></div>
        </div>
        <button class="btn btn-primary">{{ __('Create Branch') }}</button>
    </form>
</div>

<div class="card">
    @foreach($branches as $branch)
        <form method="POST" id="branchUpdate{{ $branch->id }}" action="{{ route('branches.update', $branch) }}" style="display:none">
            @csrf @method('PUT')
            <input type="hidden" name="phone" value="{{ $branch->phone }}">
        </form>
    @endforeach
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Branch number') }}</th><th>{{ __('Branch name') }}</th><th>{{ __('Address') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($branches as $branch)
                <tr>
                        <td><input form="branchUpdate{{ $branch->id }}" class="input t-mono" name="branch_number" value="{{ $branch->branch_number }}" required maxlength="32" pattern="[0-9]+"></td>
                        <td><input form="branchUpdate{{ $branch->id }}" class="input" name="name" value="{{ $branch->name }}" required maxlength="150"></td>
                        <td><input form="branchUpdate{{ $branch->id }}" class="input" name="address" value="{{ $branch->address }}"></td>
                        <td>{{ $branch->users_count }}</td>
                        <td>
                            <select form="branchUpdate{{ $branch->id }}" class="select" name="is_active">
                                <option value="1" @selected($branch->is_active)>{{ __('Active') }}</option>
                                <option value="0" @selected(! $branch->is_active)>{{ __('Inactive') }}</option>
                            </select>
                        </td>
                        <td><button form="branchUpdate{{ $branch->id }}" class="btn btn-soft btn-sm">{{ __('Save') }}</button></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><h4>{{ __('No branches found') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $branches->links('partials.pagination') }}</div>
</div>
@endsection
