<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Ploi;

use App\Deployment\Concerns\MergesEnvironment;
use App\Deployment\Contracts\EnvironmentManagerInterface;
use Ploi\Ploi;

final class PloiEnvironmentManager implements EnvironmentManagerInterface
{
    use MergesEnvironment;

    private ?Ploi $client = null;

    public function __construct(
        private readonly string $apiKey,
    ) {}

    /**
     * @return array<string>
     */
    public function plan(int $variableCount): array
    {
        if ($variableCount === 0) {
            return [];
        }

        return ["Set {$variableCount} environment variable".($variableCount === 1 ? '' : 's')];
    }

    /**
     * @param array<string, string> $variables
     *
     * @return array<string, mixed>
     */
    public function apply(int $serverId, int $siteId, array $variables): array
    {
        if ($variables === []) {
            return ['success' => true, 'message' => 'No environment variables to configure'];
        }

        try {
            $client = $this->getClient();
            $server = $client->server($serverId);
            $site = $server->sites($siteId);

            $existingResponse = $site->environment()->get();
            $existingContent = '';

            if ($existingResponse->getResponse()->getStatusCode() === 200) {
                $json = $existingResponse->getJson();
                if (isset($json->data->content) && \is_string($json->data->content)) {
                    $existingContent = $json->data->content;
                }
            }

            $mergedContent = $this->mergeEnvContent($existingContent, $variables);

            $updateResponse = $site->environment()->update($mergedContent);

            return [
                'success' => true,
                'message' => 'Environment variables configured successfully',
                'response' => $updateResponse->getJson(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to configure environment variables: {$e->getMessage()}",
            ];
        }
    }

    private function getClient(): Ploi
    {
        if ($this->client === null) {
            $this->client = new Ploi($this->apiKey);
        }

        return $this->client;
    }
}
