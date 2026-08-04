@php
    $navigationUnreadCount = auth()
        ->user()
        ->unreadNotifications()
        ->count();
@endphp

<a
    class="btn btn-sm btn-outline-light position-relative"
    href="{{ route('notifications.index') }}"
    aria-label="Notifications{{ $navigationUnreadCount > 0 ? ': '.$navigationUnreadCount.' unread' : '' }}"
>
    Notifications

    @if ($navigationUnreadCount > 0)
        <span
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
        >
            {{ $navigationUnreadCount > 99 ? '99+' : $navigationUnreadCount }}

            <span class="visually-hidden">
                unread notifications
            </span>
        </span>
    @endif
</a>
