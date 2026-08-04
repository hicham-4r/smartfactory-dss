<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class DashboardModuleCard implements JsonSerializable
{
    /**
     * @param array<string, int|string> $query
     */
    public function __construct(
        public string $key,
        public string $eyebrow,
        public string $title,
        public string $description,
        public string $routeName,
        public array $query = [],
        public string $tone = 'secondary',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'description' => $this->description,
            'route_name' => $this->routeName,
            'query' => $this->query,
            'tone' => $this->tone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
