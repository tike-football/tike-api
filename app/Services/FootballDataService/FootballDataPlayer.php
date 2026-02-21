<?php

namespace App\Services\FootballDataService;

class FootballDataPlayer
{
    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $endpoint,
        public readonly ?int $playerId,
        public readonly ?int $teamId,
        public readonly int $season,
        public readonly ?array $response = null,
        public readonly ?string $errorMessage = null,
    ) {
    }
}

