@props([
    'type' => 'empty',
    'title',
    'message',
    'actionUrl' => null,
    'actionLabel' => 'Return',
])

@php
    $normalizedType = match ($type) {
        'error', 'warning', 'not-applicable' => $type,
        default => 'empty',
    };

    $role = $normalizedType === 'error'
        ? 'alert'
        : 'status';
@endphp

<section
    {{ $attributes->class([
        'sf-state-panel',
        'sf-state-panel--'.$normalizedType,
    ]) }}
    role="{{ $role }}"
>
    <div
        class="sf-state-panel__icon"
        aria-hidden="true"
    >
        @switch($normalizedType)
            @case('error')
                !
                @break

            @case('warning')
                !
                @break

            @case('not-applicable')
                —
                @break

            @default
                0
        @endswitch
    </div>

    <div>
        <h2 class="h5 mb-2">{{ $title }}</h2>

        <p class="text-muted-smartfactory mb-0">
            {{ $message }}
        </p>

        @if ($actionUrl)
            <a
                href="{{ $actionUrl }}"
                class="btn btn-outline-primary mt-3"
            >
                {{ $actionLabel }}
            </a>
        @endif
    </div>
</section>
