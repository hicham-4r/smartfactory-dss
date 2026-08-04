<?php

namespace App\Services\AI\Explanations;

use App\DTOs\AI\Explanations\AiExplanationSnapshot;
use App\DTOs\AI\Inference\AiInferenceResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class AiExplanationSnapshotStore
{
    private const SESSION_KEY = 'ai.explanations.snapshots';

    private const SESSION_BINDING_KEY = 'ai.explanations.session_binding';

    public function __construct(
        private readonly int $ttlMinutes = 15,
        private readonly int $maximumSnapshots = 10,
    ) {}

    /**
     * @param  array<string, mixed>  $inferencePayload
     */
    public function store(
        Request $request,
        AiInferenceResult $result,
        array $inferencePayload,
        ?string $reportToken = null,
    ): ?string {
        $user = $request->user();

        if (
            ! $result->succeeded()
            || ! $user instanceof User
            || ! $user->exists
        ) {
            return null;
        }

        $token = (string) Str::uuid();
        $sessionFingerprint = $this->sessionFingerprintForStore($request);

        if ($sessionFingerprint === null) {
            return null;
        }

        $snapshot = new AiExplanationSnapshot(
            token: $token,
            userId: (int) $user->getKey(),
            sessionFingerprint: $sessionFingerprint,
            operation: $result->operation,
            inferenceRequestId: $result->requestId,
            inferencePayload: $inferencePayload,
            inferenceData: $result->data,
            reportToken: $reportToken,
            expiresAt: CarbonImmutable::now()->addMinutes(
                max(1, min(60, $this->ttlMinutes)),
            ),
        );

        try {
            $encoded = json_encode(
                $snapshot->toArray(),
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            return null;
        }

        try {
            $encrypted = Crypt::encryptString($encoded);
        } catch (Throwable) {
            return null;
        }

        $snapshots = $this->prunedSnapshots($request);
        $snapshots[$token] = $encrypted;

        if (count($snapshots) > max(1, min(25, $this->maximumSnapshots))) {
            $snapshots = array_slice(
                $snapshots,
                -max(1, min(25, $this->maximumSnapshots)),
                null,
                true,
            );
        }

        $request->session()->put(self::SESSION_KEY, $snapshots);

        return $token;
    }

    public function retrieve(
        Request $request,
        string $token,
    ): ?AiExplanationSnapshot {
        if (! Str::isUuid($token)) {
            return null;
        }

        $snapshots = $this->prunedSnapshots($request);
        $request->session()->put(self::SESSION_KEY, $snapshots);

        $encrypted = $snapshots[$token] ?? null;

        if (! is_string($encrypted)) {
            return null;
        }

        $snapshot = $this->decode($encrypted);

        if ($snapshot === null) {
            return null;
        }

        $user = $request->user();
        $sessionFingerprint = $this->sessionFingerprintForRetrieve($request);

        if (
            ! $user instanceof User
            || (int) $user->getKey() !== $snapshot->userId
            || $sessionFingerprint === null
            || ! hash_equals(
                $snapshot->sessionFingerprint,
                $sessionFingerprint,
            )
            || $snapshot->expiresAt->isPast()
        ) {
            return null;
        }

        return $snapshot;
    }

    /**
     * @return array<string, string>
     */
    private function prunedSnapshots(Request $request): array
    {
        $stored = $request->session()->get(self::SESSION_KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        $valid = [];

        foreach ($stored as $token => $encrypted) {
            if (
                ! is_string($token)
                || ! Str::isUuid($token)
                || ! is_string($encrypted)
            ) {
                continue;
            }

            $snapshot = $this->decode($encrypted);

            if ($snapshot !== null && ! $snapshot->expiresAt->isPast()) {
                $valid[$token] = $encrypted;
            }
        }

        return $valid;
    }

    private function decode(string $encrypted): ?AiExplanationSnapshot
    {
        try {
            $decoded = Crypt::decryptString($encrypted);
            $payload = json_decode(
                $decoded,
                true,
                32,
                JSON_THROW_ON_ERROR,
            );
        } catch (
            DecryptException
            | JsonException
            | Throwable
        ) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        try {
            $token = $payload['token'] ?? null;
            $userId = $payload['user_id'] ?? null;
            $sessionFingerprint = $payload['session_fingerprint'] ?? null;
            $operation = $payload['operation'] ?? null;
            $inferenceRequestId = $payload['inference_request_id'] ?? null;
            $inferencePayload = $payload['inference_payload'] ?? null;
            $inferenceData = $payload['inference_data'] ?? null;
            $reportToken = $payload['report_token'] ?? null;
            $expiresAt = $payload['expires_at'] ?? null;

            if (
                ! is_string($token)
                || ! Str::isUuid($token)
                || ! is_int($userId)
                || $userId < 1
                || ! is_string($sessionFingerprint)
                || strlen($sessionFingerprint) !== 64
                || ! is_string($operation)
                || ! is_string($inferenceRequestId)
                || ! is_array($inferencePayload)
                || ! is_array($inferenceData)
                || ! is_string($expiresAt)
                || ($reportToken !== null && ! is_string($reportToken))
            ) {
                return null;
            }

            return new AiExplanationSnapshot(
                token: $token,
                userId: $userId,
                sessionFingerprint: $sessionFingerprint,
                operation: $operation,
                inferenceRequestId: $inferenceRequestId,
                inferencePayload: $inferencePayload,
                inferenceData: $inferenceData,
                reportToken: $reportToken,
                expiresAt: CarbonImmutable::parse($expiresAt),
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function sessionFingerprintForStore(
        Request $request,
    ): ?string {
        $binding = $request->session()->get(
            self::SESSION_BINDING_KEY,
        );

        if (! $this->isValidSessionBinding($binding)) {
            try {
                $binding = bin2hex(random_bytes(32));
            } catch (Throwable) {
                return null;
            }

            $request->session()->put(
                self::SESSION_BINDING_KEY,
                $binding,
            );
        }

        return hash('sha256', $binding);
    }

    private function sessionFingerprintForRetrieve(
        Request $request,
    ): ?string {
        $binding = $request->session()->get(
            self::SESSION_BINDING_KEY,
        );

        if (! $this->isValidSessionBinding($binding)) {
            return null;
        }

        return hash('sha256', $binding);
    }

    private function isValidSessionBinding(mixed $binding): bool
    {
        return is_string($binding)
            && preg_match('/^[0-9a-f]{64}$/', $binding) === 1;
    }
}
