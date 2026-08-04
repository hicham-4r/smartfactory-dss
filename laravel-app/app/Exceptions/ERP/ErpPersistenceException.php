<?php

namespace App\Exceptions\ERP;

use App\Enums\ERP\ErpResource;
use RuntimeException;
use Throwable;

final class ErpPersistenceException extends RuntimeException
{
    /**
     * @param array<string, mixed> $safeContext
     */
    public function __construct(
        string $message,
        private readonly array $safeContext = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );
    }

    public static function missingTargetTable(
        ErpResource $resource
    ): self {
        return new self(
            message:
                'No local target table is available for the ERP resource.',

            safeContext: [
                'resource' => $resource->value,
            ]
        );
    }

    public static function invalidMappedData(
        ErpResource $resource,
        string $externalId
    ): self {
        return new self(
            message:
                'The mapped ERP entity does not contain valid persistence data.',

            safeContext: [
                'resource' => $resource->value,
                'external_id' => $externalId,
            ]
        );
    }

    public static function missingIdentity(
        ErpResource $resource,
        string $table,
        string $externalId
    ): self {
        return new self(
            message:
                'No safe local identity column is available for the ERP record.',

            safeContext: [
                'resource' => $resource->value,
                'table' => $table,
                'external_id' => $externalId,
            ]
        );
    }

    public static function missingDependency(
        ErpResource $resource,
        string $externalId,
        string $dependencyResource,
        string $dependencyExternalId
    ): self {
        return new self(
            message:
                'A required ERP dependency has not been synchronized.',

            safeContext: [
                'resource' => $resource->value,
                'external_id' => $externalId,
                'dependency_resource' => $dependencyResource,
                'dependency_external_id' =>
                    $dependencyExternalId,
            ]
        );
    }

    public static function databaseFailure(
        ErpResource $resource,
        string $table,
        string $externalId,
        Throwable $previous
    ): self {
        return new self(
            message:
                'The ERP record could not be persisted in the local database.',

            safeContext: [
                'resource' => $resource->value,
                'table' => $table,
                'external_id' => $externalId,
            ],

            previous: $previous
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->safeContext;
    }
}