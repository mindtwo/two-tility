<?php

namespace mindtwo\TwoTility\Tests\Mock;

use Illuminate\Http\Client\Response;
use mindtwo\TwoTility\Http\BaseApiClient;

class TestApiClient extends BaseApiClient
{
    public function apiName(): string
    {
        return 'test-api';
    }

    protected function configBaseKey(): string
    {
        return 'test-api';
    }

    /**
     * Get data from the API.
     */
    public function getData(string $id): Response
    {
        return $this->client()->get("/data/{$id}");
    }

    /**
     * Create data via the API.
     */
    public function createData(array $data): Response
    {
        return $this->client()->post('/data', $data);
    }

    /**
     * Update data via the API.
     */
    public function updateData(string $id, array $data): Response
    {
        return $this->client()->put("/data/{$id}", $data);
    }

    /**
     * Partially update data via the API.
     */
    public function patchData(string $id, array $data): Response
    {
        return $this->client()->patch("/data/{$id}", $data);
    }

    /**
     * Delete data via the API.
     */
    public function deleteData(string $id): Response
    {
        return $this->client()->delete("/data/{$id}");
    }

    /**
     * Search with query parameters.
     */
    public function searchData(array $query): Response
    {
        return $this->client()->get('/data', $query);
    }
}
