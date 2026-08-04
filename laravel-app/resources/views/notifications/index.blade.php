@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4"
    >
        <div>
            <div class="text-uppercase small text-muted fw-semibold">
                Personal notification center
            </div>

            <h1 class="h3 mb-1">
                Notifications
            </h1>

            <p class="text-muted mb-0">
                Operational alerts and workflow updates visible only to your account.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary">
                {{ $unreadCount }} unread
            </span>

            @if ($unreadCount > 0)
                <form
                    method="POST"
                    action="{{ route('notifications.read-all') }}"
                    class="m-0"
                >
                    @csrf
                    @method('PATCH')

                    <button
                        type="submit"
                        class="btn btn-outline-primary"
                    >
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </div>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form
                method="GET"
                action="{{ route('notifications.index') }}"
                class="row g-3 align-items-end"
            >
                <div class="col-12 col-md-4">
                    <label
                        for="status"
                        class="form-label"
                    >
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >
                        @foreach ([
                            'all' => 'All notifications',
                            'unread' => 'Unread only',
                            'read' => 'Read only',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($statusFilter === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label
                        for="severity"
                        class="form-label"
                    >
                        Severity
                    </label>

                    <select
                        id="severity"
                        name="severity"
                        class="form-select"
                    >
                        @foreach ([
                            'all' => 'All severities',
                            'information' => 'Information',
                            'warning' => 'Warning',
                            'critical' => 'Critical',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($severityFilter === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply filters
                    </button>

                    <a
                        href="{{ route('notifications.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </section>

    @forelse ($notifications as $notification)
        @php
            $data = is_array($notification->data)
                ? $notification->data
                : [];

            $severity = \App\Enums\Notifications\NotificationSeverity
                ::tryFrom(
                    (string) ($data['severity'] ?? '')
                )
                ?? \App\Enums\Notifications\NotificationSeverity
                    ::Information;

            $isUnread = $notification->read_at === null;

            $actionUrl = (string) (
                $data['action_url']
                ?? '/dashboard'
            );

            $safeActionUrl = str_starts_with(
                $actionUrl,
                '/'
            ) && ! str_starts_with(
                $actionUrl,
                '//'
            )
                ? $actionUrl
                : '/dashboard';
        @endphp

        <article
            class="card shadow-sm mb-3 {{ $isUnread ? 'border-primary' : 'border-0' }}"
        >
            <div class="card-body">
                <div
                    class="d-flex flex-column flex-lg-row justify-content-between gap-3"
                >
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span
                                class="badge text-bg-{{ $severity->bootstrapClass() }}"
                            >
                                {{ $severity->label() }}
                            </span>

                            <span class="badge text-bg-light border">
                                {{ $data['category'] ?? 'notification' }}
                            </span>

                            @if ($isUnread)
                                <span class="badge text-bg-primary">
                                    Unread
                                </span>
                            @endif
                        </div>

                        <h2 class="h5 mb-2">
                            {{ $data['title'] ?? 'Notification' }}
                        </h2>

                        <p class="mb-2">
                            {{ $data['message'] ?? '' }}
                        </p>

                        <div class="small text-muted">
                            {{ $notification->created_at?->diffForHumans() }}

                            @if ($notification->read_at !== null)
                                · Read
                                {{ $notification->read_at->diffForHumans() }}
                            @endif
                        </div>
                    </div>

                    <div
                        class="d-flex flex-column flex-sm-row align-items-start gap-2"
                    >
                        <a
                            href="{{ $safeActionUrl }}"
                            class="btn btn-primary"
                        >
                            {{ $data['action_label'] ?? 'Open' }}
                        </a>

                        @if ($isUnread)
                            <form
                                method="POST"
                                action="{{ route(
                                    'notifications.read',
                                    $notification->getKey()
                                ) }}"
                                class="m-0"
                            >
                                @csrf
                                @method('PATCH')

                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary"
                                >
                                    Mark as read
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="card border-0 shadow-sm">
            <div class="card-body py-5 text-center">
                <h2 class="h5">
                    No notifications found
                </h2>

                <p class="text-muted mb-0">
                    No notifications match the selected filters.
                </p>
            </div>
        </div>
    @endforelse

    @if ($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
@endsection
