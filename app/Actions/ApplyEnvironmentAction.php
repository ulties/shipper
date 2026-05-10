<?php

declare(strict_types=1);

namespace App\Actions;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\Providers\Ploi\PloiEnvironmentManager;

final class ApplyEnvironmentAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(
        string $providerName,
        string $apiKey,
        int $serverId,
        int $siteId,
        ProjectConfig $project,
        ProfileConfig $profile,
    ): array {
        if ($providerName !== 'ploi') {
            return ['success' => false, 'message' => "Provider {$providerName} not supported for environment variables"];
        }

        $projectEnv = $project->environment();
        $profileEnv = $profile->environment();

        $mergedEnv = $projectEnv->mergeWith($profileEnv);
        $variables = $mergedEnv->variables();

        if ($variables === []) {
            return ['success' => true, 'message' => 'No environment variables to configure'];
        }

        $manager = new PloiEnvironmentManager($apiKey);

        return $manager->apply($serverId, $siteId, $variables);
    }
}
