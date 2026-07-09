<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class LaravelCloud
{
    /**
     * Create a new Laravel Cloud API client instance.
     */
    public function __construct(
        protected string $key,
        protected string $url = 'https://cloud.laravel.com/api',
    ) {}

    /**
     * Get all applications for the authenticated organization.
     *
     * @return list<array<string, mixed>>
     */
    public function applications(): array
    {
        return $this->fetchAllPages('/applications');
    }

    /**
     * Get all environments for the given application.
     *
     * @return list<array<string, mixed>>
     */
    public function environments(string $applicationId): array
    {
        return $this->fetchAllPages("/applications/{$applicationId}/environments");
    }

    /**
     * Get billing and usage data for the authenticated organization.
     *
     * @return array<string, mixed>
     */
    public function usage(int $period = 0): array
    {
        return (array) $this->request()->get('/usage', ['period' => $period])->throw()->json();
    }

    /**
     * Fetch every page of a paginated collection endpoint.
     *
     * @return list<array<string, mixed>>
     */
    protected function fetchAllPages(string $path): array
    {
        $records = [];
        $url = $path;
        $pages = 0;

        do {
            $response = (array) $this->request()->get($url)->throw()->json();

            $data = $response['data'] ?? [];
            $records = array_merge($records, is_array($data) ? array_values($data) : []);

            $next = data_get($response, 'links.next');
            $url = is_string($next) && $next !== '' ? $next : null;
        } while ($url !== null && ++$pages < 100);

        return $records;
    }

    /**
     * Build a pending request for the Laravel Cloud API.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->url)
            ->withToken($this->key)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->retry(3, 250, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()));
    }
}
