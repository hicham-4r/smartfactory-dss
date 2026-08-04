<?php

namespace App\Services\Reports;

use RuntimeException;

final class StoredZipWriter
{
    /**
     * @var list<array{
     *     name:string,
     *     data:string,
     *     crc:int,
     *     size:int,
     *     offset:int
     * }>
     */
    private array $files = [];

    private string $body = '';

    public function add(
        string $name,
        string $data
    ): void {
        $name = str_replace(
            '\\',
            '/',
            ltrim($name, '/')
        );

        if (
            $name === ''
            || str_contains($name, '../')
        ) {
            throw new RuntimeException(
                'The ZIP entry name is invalid.'
            );
        }

        $nameBytes = $name;
        $size = strlen($data);
        $crc = (int) sprintf(
            '%u',
            crc32($data)
        );
        $offset = strlen($this->body);
        $flags = 0x0800;

        $this->body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            0,
            0,
            0,
            $crc,
            $size,
            $size,
            strlen($nameBytes),
            0
        );

        $this->body .= $nameBytes;
        $this->body .= $data;

        $this->files[] = [
            'name' => $nameBytes,
            'data' => $data,
            'crc' => $crc,
            'size' => $size,
            'offset' => $offset,
        ];
    }

    public function finish(): string
    {
        $centralDirectory = '';
        $flags = 0x0800;

        foreach ($this->files as $file) {
            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                $flags,
                0,
                0,
                0,
                $file['crc'],
                $file['size'],
                $file['size'],
                strlen($file['name']),
                0,
                0,
                0,
                0,
                0,
                $file['offset']
            );

            $centralDirectory .= $file['name'];
        }

        $centralOffset = strlen(
            $this->body
        );

        $centralSize = strlen(
            $centralDirectory
        );

        $count = count($this->files);

        $endRecord = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0
        );

        return $this->body
            .$centralDirectory
            .$endRecord;
    }
}
