@extends('layouts.app')
@section('title', 'New message')
@section('crumb', 'Contact a colleague or announce to your branch')

@section('content')
<div class="page-head">
    <div class="titles">
        <h2>{{ __('New message') }}</h2>
        <p>{{ __('Messages are visible only to their participants.') }}</p>
    </div>
    <a class="btn btn-light" href="{{ route('messages.index') }}">@include('partials.icon', ['name' => 'arrow-left']) {{ __('Back to messages') }}</a>
</div>

<form class="card message-compose-card" method="POST" action="{{ route('messages.store') }}">
    @csrf
    <div class="field">
        <label for="messageType">{{ __('Message type') }}</label>
        <select class="select" id="messageType" name="type" required>
            <option value="direct" @selected(old('type', 'direct') === 'direct')>{{ __('Direct message') }}</option>
            @if($canAnnounce)
                <option value="announcement" @selected(old('type') === 'announcement')>{{ __('Branch announcement') }}</option>
            @endif
        </select>
        @error('type')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field" id="directRecipient">
        <label for="messageRecipient">{{ __('Send to') }}</label>
        <select class="select" id="messageRecipient" name="recipient_id">
            <option value="">{{ __('Choose a colleague') }}</option>
            @foreach($recipients as $recipient)
                <option value="{{ $recipient->id }}" @selected((string) old('recipient_id') === (string) $recipient->id)>
                    {{ $recipient->name }} · {{ $recipient->email }}
                    @if($isSuperAdmin && $recipient->branch_id)
                        · {{ $recipient->branch?->name }}
                    @endif
                </option>
            @endforeach
        </select>
        @error('recipient_id')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="message-announcement-fields" id="announcementFields" hidden>
        @if($isSuperAdmin)
            <div class="field">
                <label for="messageBranch">{{ __('Branch') }}</label>
                <select class="select" id="messageBranch" name="branch_id">
                    <option value="">{{ __('Choose a branch') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->branch_number }} · {{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        @endif
        <div class="field">
            <label for="messageAudience">{{ __('Send announcement to') }}</label>
            <select class="select" id="messageAudience" name="audience">
                <option value="branch" @selected(old('audience', 'branch') === 'branch')>{{ __('Everyone in branch') }}</option>
                <option value="role" @selected(old('audience') === 'role')>{{ __('A role in branch') }}</option>
            </select>
            @error('audience')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="field" id="announcementRole">
            <label for="messageRole">{{ __('Role') }}</label>
            <select class="select" id="messageRole" name="role">
                <option value="">{{ __('Choose a role') }}</option>
                @foreach($roles as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                @endforeach
            </select>
            @error('role')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <p class="message-privacy-note">{{ __('Announcements are read-only and delivered only to active users in the selected branch.') }}</p>
    </div>

    <div class="field" id="announcementSubject" hidden>
        <label for="messageSubject">{{ __('Subject') }}</label>
        <input class="input" id="messageSubject" name="subject" value="{{ old('subject') }}" maxlength="150">
        @error('subject')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="messageBody">{{ __('Message') }}</label>
        <textarea class="input message-body-input" id="messageBody" name="body" rows="7" maxlength="5000" required>{{ old('body') }}</textarea>
        @error('body')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="message-compose-actions">
        <a class="btn btn-light" href="{{ route('messages.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary">@include('partials.icon', ['name' => 'send']) {{ __('Send message') }}</button>
    </div>
</form>

@push('scripts')
<script>
    (() => {
        const type = document.getElementById('messageType');
        const directRecipient = document.getElementById('directRecipient');
        const announcementFields = document.getElementById('announcementFields');
        const announcementSubject = document.getElementById('announcementSubject');
        const subject = document.getElementById('messageSubject');
        const audience = document.getElementById('messageAudience');
        const announcementRole = document.getElementById('announcementRole');

        const updateFields = () => {
            const isAnnouncement = type.value === 'announcement';
            directRecipient.hidden = isAnnouncement;
            announcementFields.hidden = !isAnnouncement;
            announcementSubject.hidden = !isAnnouncement;
            subject.required = isAnnouncement;
            announcementRole.hidden = !isAnnouncement || audience.value !== 'role';
        };

        type.addEventListener('change', updateFields);
        audience?.addEventListener('change', updateFields);
        updateFields();
    })();
</script>
@endpush
@endsection
