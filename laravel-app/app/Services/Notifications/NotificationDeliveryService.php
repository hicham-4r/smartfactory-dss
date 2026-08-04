<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\SmartFactoryAlertNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class NotificationDeliveryService
{
    public function send(
        User $recipient,
        SmartFactoryAlertNotification $notification
    ): bool {
        if (! $recipient->is_active) {
            return false;
        }

        $payload = $notification->toDatabase(
            $recipient
        );

        $dedupeKey = hash(
            'sha256',
            $recipient->getMorphClass()
            .'|'
            .$recipient->getKey()
            .'|'
            .$notification->fingerprint
        );

        try {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'notifiable_type' =>
                    $recipient->getMorphClass(),
                'notifiable_id' =>
                    $recipient->getKey(),
                'data' => json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                ),
                'severity' =>
                    $notification->severity->value,
                'category' =>
                    mb_substr(
                        trim($notification->category),
                        0,
                        80
                    ),
                'read_at' => null,
                'dedupe_key' => $dedupeKey,
                'expires_at' =>
                    $notification->expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation(
                $exception
            )) {
                return false;
            }

            throw $exception;
        } catch (JsonException $exception) {
            throw new \RuntimeException(
                'The notification payload could not be encoded.',
                previous: $exception
            );
        }

        return true;
    }

    /**
     * @param iterable<User> $recipients
     */
    public function sendToMany(
        iterable $recipients,
        SmartFactoryAlertNotification $notification
    ): int {
        $created = 0;
        $seen = [];

        foreach ($recipients as $recipient) {
            $recipientId = (int) $recipient->getKey();

            if (
                $recipientId < 1
                || isset($seen[$recipientId])
            ) {
                continue;
            }

            $seen[$recipientId] = true;

            if ($this->send(
                $recipient,
                $notification
            )) {
                $created++;
            }
        }

        return $created;
    }

    private function isUniqueConstraintViolation(
        QueryException $exception
    ): bool {
        $sqlState = (string) $exception->getCode();

        if (in_array(
            $sqlState,
            [
                '23000',
                '23505',
            ],
            true
        )) {
            return true;
        }

        $message = mb_strtolower(
            $exception->getMessage()
        );

        return str_contains(
            $message,
            'unique constraint'
        )
            || str_contains(
                $message,
                'duplicate entry'
            );
    }
}
