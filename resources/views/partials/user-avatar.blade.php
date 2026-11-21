@php
    $class = $class ?? 'avatar';
    $style = $style ?? '';
    $initials = $initials ?? mb_strtoupper(mb_substr(trim($user->name), 0, 1));
@endphp
<div class="{{ $class }}" @if($style) style="{{ $style }}" @endif>
    @if($user->avatar_url)
        <img class="user-avatar-image" src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
    @else
        <span>{{ $initials }}</span>
    @endif
</div>
