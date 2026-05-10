<?php

declare(strict_types=1);

namespace App\Deployment\Contracts;

interface EnvironmentManagerInterface
{
    /**
     * Plan environment variable configuration.
     *
     * @return array<string>
     */
    public function plan(int $variableCount): array;

    /**
     * Apply environment variable configuration.
     *
     * @param array<string, string> $variables
     *
     * @return array<string, mixed>
     */
    public function apply(int $serverId, int $siteId, array $variables): array;
}
