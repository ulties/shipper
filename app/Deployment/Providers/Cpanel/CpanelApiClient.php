<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Cpanel;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class CpanelApiClient
{
    private ?Client $httpClient = null;

    private string $baseUrl;

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function formatResponse(array $result): array
    {
        return [
            'success' => ($result['status'] ?? 0) === 1,
            'message' => $this->extractMessage($result),
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function extractMessage(array $result): string
    {
        $errors = $result['errors'] ?? null;
        if (\is_array($errors) && isset($errors[0]) && \is_string($errors[0])) {
            return $errors[0];
        }

        $messages = $result['messages'] ?? null;
        if (\is_array($messages) && isset($messages[0]) && \is_string($messages[0])) {
            return $messages[0];
        }

        return 'OK';
    }

    public function __construct(
        string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $authType,
        private readonly string $credential,
    ) {
        $protocol = $this->port === 2096 || $this->port === 2095 ? 'http' : 'https';
        $this->baseUrl = "{$protocol}://{$host}:{$this->port}/execute";
    }

    /**
     * @return array<string, mixed>
     */
    public function createGitRepository(string $cloneUrl, string $repositoryPath, string $repositoryName): array
    {
        return $this->request('Git::create_repository', [
            'clone_url' => $cloneUrl,
            'repository_path' => $repositoryPath,
            'repository_name' => $repositoryName,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listGitRepositories(): array
    {
        return $this->request('Git::list_repositories', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteGitRepository(string $repositoryPath): array
    {
        return $this->request('Git::delete_repository', [
            'repository_path' => $repositoryPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pullGitRepository(string $repositoryPath): array
    {
        return $this->request('Git::pull', [
            'repository_path' => $repositoryPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deployGitRepository(string $repositoryPath): array
    {
        return $this->request('Git::deploy', [
            'repository_path' => $repositoryPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDatabase(string $databaseName): array
    {
        return $this->request('Database::create_database', [
            'name' => $databaseName,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDatabaseUser(string $databaseName, string $username, string $password): array
    {
        return $this->request('Database::create_user', [
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * @param array<string> $privileges
     *
     * @return array<string, mixed>
     */
    public function addDatabaseUserToDatabase(string $database, string $username, array $privileges): array
    {
        return $this->request('Database::add_user_database_privileges', [
            'database' => $database,
            'username' => $username,
            'privileges' => $privileges,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listDomains(): array
    {
        return $this->request('Domain::list_domains', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSubdomain(string $domain, string $subdomain): array
    {
        return $this->request('SubDomain::create_subdomain', [
            'domain' => $domain,
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function installSsl(string $domain, string $cert, string $key, string $ca = ''): array
    {
        $params = [
            'domain' => $domain,
            'cert' => $cert,
            'key' => $key,
        ];

        if ($ca !== '') {
            $params['ca_bundle'] = $ca;
        }

        return $this->request('SSL::install_ssl', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSslCertificate(string $domain): array
    {
        return $this->request('SSL::list_ssl', [
            'domain' => $domain,
        ]);
    }

    /**
     * @param array<string, array<string>|string> $params
     *
     * @return array<string, mixed>
     */
    private function request(string $moduleFunction, array $params): array
    {
        $client = $this->getHttpClient();

        [$module, $function] = \explode('::', $moduleFunction, 2);
        $url = "{$this->baseUrl}/{$module}/{$function}";

        $headers = $this->getAuthHeaders();

        try {
            $response = $client->request('GET', $url, [
                'headers' => $headers,
                'query' => $params,
            ]);

            /** @var string $body */
            $body = (string) $response->getBody();
            /** @var array<string, mixed> $data */
            $data = \json_decode($body, true) ?? [];

            return $this->formatResponse($data);
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    private function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'timeout' => 30,
                'verify' => true,
            ]);
        }

        return $this->httpClient;
    }

    /**
     * @return array<string, string>
     */
    private function getAuthHeaders(): array
    {
        if ($this->authType === 'api_token') {
            return [
                'Authorization' => 'cpanel '.$this->username.':'.$this->credential,
            ];
        }

        $auth = \base64_encode($this->username.':'.$this->credential);

        return [
            'Authorization' => 'Basic '.$auth,
        ];
    }
}
