<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Cpanel;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;

final class CpanelProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?CpanelApiClient $apiClient = null;

    public function getName(): string
    {
        return 'cpanel';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): CpanelApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new CpanelApiClient(
                $this->getHost(),
                $this->getPort(),
                $this->getUsername(),
                $this->getAuthType(),
                $this->getCredential(),
            );
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $host = $this->config['host'] ?? null;
        if (! \is_string($host) || $host === '') {
            $errors[] = 'cPanel host is required (e.g., cpanel.example.com)';
        }

        $port = $this->config['port'] ?? null;
        if (! \is_int($port) && (! \is_string($port) || $port === '')) {
            $errors[] = 'cPanel port is required (2083 for SSL, 2082 for non-SSL)';
        }

        $username = $this->config['username'] ?? null;
        if (! \is_string($username) || $username === '') {
            $errors[] = 'cPanel username is required';
        }

        $hasPassword = isset($this->config['password']) && \is_string($this->config['password']) && $this->config['password'] !== '';
        $hasToken = isset($this->config['api_token']) && \is_string($this->config['api_token']) && $this->config['api_token'] !== '';

        if (! $hasPassword && ! $hasToken) {
            $errors[] = 'cPanel password or API token is required';
        }

        $domain = $profile->get('domain');
        if ($domain === null || $domain === '') {
            $errors[] = "Domain is required for profile: {$profile->name()}";
        }

        return $errors;
    }

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $repository = $project->repository();
        $repoProviderValue = $repository['provider'] ?? 'unknown';
        $repoProvider = \is_string($repoProviderValue) ? $repoProviderValue : 'unknown';
        $repoNameValue = $repository['name'] ?? 'unknown';
        $repoName = \is_string($repoNameValue) ? $repoNameValue : 'unknown';
        $branch = $profile->branch();

        $actions = [
            "Configure domain: {$domain}",
            "Clone repository: {$repoProvider}:{$repoName} ({$branch})",
        ];

        $databases = $project->databases();
        if (! empty($databases)) {
            foreach ($databases as $database) {
                $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                $actions[] = "Create database: {$dbName}";
            }
        }

        $actions[] = 'Deploy via cPanel Git Version Control';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'web_directory' => $project->webDirectory(),
            'project_root' => $project->projectRoot(),
            'databases' => \array_map(
                fn ($db) => [
                    'name' => $this->interpolateDatabaseName($db->name(), $project->name(), $profile->name()),
                    'user' => $this->interpolateDatabaseName($db->user(), $project->name(), $profile->name()),
                    'type' => $db->type(),
                ],
                $databases,
            ),
            'actions' => $actions,
            'note' => 'This will configure deployment via cPanel UAPI on '.$this->getHost(),
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $branch = $profile->branch();
        $repository = $project->repository();
        $repoUrlRaw = $repository['url'] ?? '';
        $repoUrl = \is_string($repoUrlRaw) ? $repoUrlRaw : '';

        if ($repoUrl === '') {
            $this->lastError = 'Repository URL is required';

            return false;
        }

        try {
            $client = $this->getApiClient();

            $result = $client->createGitRepository(
                $repoUrl,
                $this->getRepositoryPath($domain),
                $this->getRepositoryName($project->name()),
            );

            if (! $result['success']) {
                $message = $result['message'] ?? 'Failed to create repository';
                $this->lastError = \is_string($message) ? $message : 'Failed to create repository';

                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        return true;
    }

    private function getHost(): string
    {
        $host = $this->config['host'] ?? '';

        return \is_string($host) ? $host : '';
    }

    private function getPort(): int
    {
        $port = $this->config['port'] ?? 2083;

        if (\is_int($port)) {
            return $port;
        }

        if (\is_string($port) && \is_numeric($port)) {
            return (int) $port;
        }

        return 2083;
    }

    private function getUsername(): string
    {
        $username = $this->config['username'] ?? '';

        return \is_string($username) ? $username : '';
    }

    private function getAuthType(): string
    {
        $token = $this->config['api_token'] ?? null;

        return (\is_string($token) && $token !== '') ? 'api_token' : 'password';
    }

    private function getCredential(): string
    {
        $token = $this->config['api_token'] ?? null;
        if (\is_string($token) && $token !== '') {
            return $token;
        }

        $password = $this->config['password'] ?? '';

        return \is_string($password) ? $password : '';
    }

    private function getRepositoryPath(string $domain): string
    {
        $basePath = $this->config['repository_path'] ?? '/';

        if (! \is_string($basePath)) {
            $basePath = '/';
        }

        return "/{$this->getUsername()}{$basePath}{$domain}";
    }

    private function getRepositoryName(string $projectName): string
    {
        return $projectName;
    }

    private function interpolateDatabaseName(string $name, string $projectName, string $profileName): string
    {
        $name = \str_replace('${PROJECT_NAME}', $projectName, $name);
        $name = \str_replace('${PROFILE}', $profileName, $name);

        return $name;
    }
}
