<?php

namespace App\Notifications;

use App\Enums\Notifications\NotificationSeverity;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class SmartFactoryAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, bool|float|int|string|null> $metadata
     */
    public function __construct(
        public readonly NotificationSeverity $severity,
        public readonly string $category,
        public readonly string $title,
        public readonly string $message,
        public readonly string $actionUrl,
        public readonly string $actionLabel,
        public readonly string $fingerprint,
        public readonly array $metadata = [],
        public readonly ?CarbonImmutable $expiresAt = null,
    ) {
        if (trim($fingerprint) === '') {
            throw new \InvalidArgumentException(
                'A notification fingerprint is required.'
            );
        }
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'severity' => $this->severity->value,
            'category' => Str::limit(
                trim($this->category),
                80,
                ''
            ),
            'title' => Str::limit(
                trim($this->title),
                160,
                ''
            ),
            'message' => Str::limit(
                trim($this->message),
                1200,
                ''
            ),
            'action_url' => $this->safeInternalUrl(
                $this->actionUrl
            ),
            'action_label' => Str::limit(
                trim($this->actionLabel),
                80,
                ''
            ),
            'fingerprint' => hash(
                'sha256',
                $this->fingerprint
            ),
            'metadata' => $this->safeMetadata(
                $this->metadata
            ),
        ];
    }

    private function safeInternalUrl(
        string $url
    ): string {
        $url = trim($url);

        if (
            $url === ''
            || ! str_starts_with($url, '/')
            || str_starts_with($url, '//')
            || str_contains($url, "\r")
            || str_contains($url, "\n")
        ) {
            return '/dashboard';
        }

        return Str::limit(
            $url,
            1000,
            ''
        );
    }

    /**
     * @param array<string, bool|float|int|string|null> $metadata
     *
     * @return array<string, bool|float|int|string|null>
     */
    private function safeMetadata(
        array $metadata
    ): array {
        $safe = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = Str::snake(
                Str::limit(
                    trim((string) $key),
                    80,
                    ''
                )
            );

            if ($normalizedKey === '') {
                continue;
            }

            $safe[$normalizedKey] = is_string($value)
                ? Str::limit($value, 500, '')
                : $value;
        }

        return $safe;
    }
}
