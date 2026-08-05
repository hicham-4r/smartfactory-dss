<?php

namespace App\Services\AI\Datasets;

use InvalidArgumentException;

final class DatasetRootGuard
{
    public function normalize(
        mixed $configuredRoot
    ): string {
        if (! is_string($configuredRoot)) {
            throw new InvalidArgumentException(
                'The AI dataset root must be a string.'
            );
        }

        $root = trim($configuredRoot);

        if (
            $root === ''
            || strlen($root) > 4096
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $root
            ) === 1
        ) {
            throw new InvalidArgumentException(
                'The AI dataset root is empty or invalid.'
            );
        }

        $portable = str_replace(
            '\\',
            '/',
            $root
        );

        $isWindowsAbsolute =
            preg_match(
                '/^[A-Za-z]:\/.+/',
                $portable
            ) === 1;

        $isUncAbsolute =
            str_starts_with(
                $portable,
                '//'
            );

        $isPosixAbsolute =
            str_starts_with(
                $portable,
                '/'
            );

        if (
            ! $isWindowsAbsolute
            && ! $isUncAbsolute
            && ! $isPosixAbsolute
        ) {
            throw new InvalidArgumentException(
                'The AI dataset root must be an absolute path.'
            );
        }

        if (
            preg_match(
                '/^[A-Za-z]:\/?$/',
                $portable
            ) === 1
            || $portable === '/'
        ) {
            throw new InvalidArgumentException(
                'The AI dataset root cannot be a filesystem root.'
            );
        }

        $normalized = rtrim(
            $portable,
            '/'
        );

        $publicPath = strtolower(
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    public_path()
                ),
                '/'
            )
        );

        $lowerRoot = strtolower(
            $normalized
        );

        if (
            $lowerRoot === $publicPath
            || str_starts_with(
                $lowerRoot,
                $publicPath.'/'
            )
        ) {
            throw new InvalidArgumentException(
                'AI datasets cannot be stored under the public web root.'
            );
        }

        return DIRECTORY_SEPARATOR === '\\'
            ? str_replace(
                '/',
                '\\',
                $normalized
            )
            : $normalized;
    }
}
