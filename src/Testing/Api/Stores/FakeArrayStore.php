<?php

namespace mindtwo\TwoTility\Testing\Api\Stores;

class FakeArrayStore
{
    /**
     * The memory store for the fake API.
     * [path][scope][id => data]
     * TODO: persist this store to a file or database if needed.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $store = [];

    public function __construct(

    ) {
        // Initialize the store if needed
        $this->store = [];

        $this->loadFromFile(storage_path('api-fake.store'));

        // Register a shutdown function to save the store
        register_shutdown_function(function () {
            $this->saveToFile(storage_path('api-fake.store'));
        });
    }

    /**
     * Add data to the store.
     */
    public function add(string $path, string $scope, string $id, mixed $data): void
    {
        if ($this->has($path, $scope, $id)) {
            // If the data already exists, we can choose to update it or throw an error.
            // Here we will just overwrite it.
            return;
        }

        $this->store[$path][$scope][$id] = $data;
    }

    /**
     * Set or update data in the store.
     *
     * @param  mixed  $data  The data to store.
     */
    public function put(string $path, string $scope, string $id, mixed $data): void
    {
        $this->store[$path][$scope][$id] = $data;
    }

    /**
     * Retrieve data from the store.
     *
     * @return mixed|null
     */
    public function get(string $path, string $scope, ?string $id = null): mixed
    {
        if ($id === null) {
            // Return the entire collection for the given path and scope
            return $this->store[$path][$scope] ?? [];
        }

        return $this->store[$path][$scope][$id] ?? null;
    }

    /**
     * Check if data exists for the given path, scope, and ID.
     */
    public function has(string $path, string $scope, ?string $id = null): bool
    {
        if ($id === null) {
            // Check if the path and scope exist, regardless of ID
            return isset($this->store[$path][$scope]);
        }

        return isset($this->store[$path][$scope][$id]);
    }

    /**
     * Check if the store is empty for the given path and scope.
     */
    public function isEmpty(?string $path = null, ?string $scope = null): bool
    {
        if ($path === null) {
            // Check if the entire store is empty
            return empty($this->store);
        }

        if ($scope === null) {
            // Check if the specific path is empty
            return empty($this->store[$path] ?? []);
        }

        return empty($this->store[$path][$scope]);
    }

    /**
     * Remove data from the store.
     */
    public function remove(string $path, string $scope, string $id): void
    {
        unset($this->store[$path][$scope][$id]);
    }

    /**
     * Clear the entire store.
     */
    public function clear(): void
    {
        $this->store = [];
    }

    // Load and save

    /**
     * Save the current store to a file.
     *
     * @param  string  $file  The file path to save the store.
     */
    public function saveToFile(string $file): void
    {
        file_put_contents($file, serialize($this->store));
    }

    /**
     * Load the store from a file.
     *
     * @param  string  $file  The file path to load the store from.
     */
    public function loadFromFile(string $file): void
    {
        if (file_exists($file)) {
            $this->store = unserialize(file_get_contents($file)) ?: [];
        }
    }
}
