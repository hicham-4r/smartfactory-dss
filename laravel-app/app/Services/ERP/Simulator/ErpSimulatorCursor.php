<?php

namespace App\Services\ERP\Simulator;

use App\Enums\ERP\ErpResource;
use InvalidArgumentException;
use JsonException;

final class ErpSimulatorCursor
{
    public function encode(
        ErpResource $resource,
        int $page
    ): string {
        if ($page < 1) {
            throw new InvalidArgumentException(
                'Cursor page must be at least one.'
            );
        }

        $payload = json_encode(
            [
                'resource' =>
                    $resource->value,

                'page' => $page,

                'expires_at' =>
                    time()
                    + $this->timeToLiveSeconds(),
            ],
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
        );

        $encodedPayload =
            $this->base64UrlEncode(
                $payload
            );

        $signature =
            $this->base64UrlEncode(
                hash_hmac(
                    'sha256',
                    $encodedPayload,
                    $this->secret(),
                    true
                )
            );

        return $encodedPayload
            .'.'
            .$signature;
    }

    public function decode(
        string $cursor,
        ErpResource $expectedResource
    ): int {
        $parts = explode(
            '.',
            trim($cursor)
        );

        if (count($parts) !== 2) {
            throw new InvalidArgumentException(
                'The ERP cursor format is invalid.'
            );
        }

        [
            $encodedPayload,
            $providedSignature,
        ] = $parts;

        $expectedSignature =
            $this->base64UrlEncode(
                hash_hmac(
                    'sha256',
                    $encodedPayload,
                    $this->secret(),
                    true
                )
            );

        if (
            ! hash_equals(
                $expectedSignature,
                $providedSignature
            )
        ) {
            throw new InvalidArgumentException(
                'The ERP cursor signature is invalid.'
            );
        }

        try {
            $payload = json_decode(
                $this->base64UrlDecode(
                    $encodedPayload
                ),
                true,
                32,
                JSON_THROW_ON_ERROR
            );
        } catch (
            JsonException
            | InvalidArgumentException
        ) {
            throw new InvalidArgumentException(
                'The ERP cursor payload is invalid.'
            );
        }

        if (
            ! is_array($payload)
            || (
                $payload['resource']
                ?? null
            ) !== $expectedResource->value
            || ! is_int(
                $payload['page']
                ?? null
            )
            || $payload['page'] < 1
            || ! is_int(
                $payload['expires_at']
                ?? null
            )
        ) {
            throw new InvalidArgumentException(
                'The ERP cursor data is invalid.'
            );
        }

        if (
            $payload['expires_at']
            < time()
        ) {
            throw new InvalidArgumentException(
                'The ERP cursor has expired.'
            );
        }

        return $payload['page'];
    }

    private function secret(): string
    {
        $secret = trim(
            (string) config(
                'erp.simulator.token',
                ''
            )
        );

        if ($secret === '') {
            throw new InvalidArgumentException(
                'The ERP cursor secret is not configured.'
            );
        }

        return $secret;
    }

    private function timeToLiveSeconds(): int
    {
        return max(
            60,
            min(
                86400,
                (int) config(
                    'erp.simulator.cursor_ttl_seconds',
                    3600
                )
            )
        );
    }

    private function base64UrlEncode(
        string $value
    ): string {
        return rtrim(
            strtr(
                base64_encode($value),
                '+/',
                '-_'
            ),
            '='
        );
    }

    private function base64UrlDecode(
        string $value
    ): string {
        $padding =
            strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat(
                '=',
                4 - $padding
            );
        }

        $decoded = base64_decode(
            strtr(
                $value,
                '-_',
                '+/'
            ),
            true
        );

        if ($decoded === false) {
            throw new InvalidArgumentException(
                'The ERP cursor encoding is invalid.'
            );
        }

        return $decoded;
    }
}