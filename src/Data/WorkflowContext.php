<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Data;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class WorkflowContext implements ArrayAccess, Arrayable
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    public function __construct(array $data = [], array $metadata = [])
    {
        $this->data = $data;
        $this->metadata = $metadata;
    }

    /**
     * Create a new context from a model.
     */
    public static function fromModel(Model $model, string $key = 'model'): static
    {
        return new static([$key => $model]);
    }

    /**
     * Get a value from the context using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Set a value in the context using dot notation.
     */
    public function set(string $key, mixed $value): static
    {
        data_set($this->data, $key, $value);

        return $this;
    }

    /**
     * Check if a key exists in the context.
     */
    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * Get all data from the context.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Merge additional data into the context.
     *
     * @param array<string, mixed> $data
     */
    public function merge(array $data): static
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value.
     */
    public function setMeta(string $key, mixed $value): static
    {
        data_set($this->metadata, $key, $value);

        return $this;
    }

    /**
     * Get all metadata.
     *
     * @return array<string, mixed>
     */
    public function getAllMeta(): array
    {
        return $this->metadata;
    }

    /**
     * Clone the context with additional data.
     *
     * @param array<string, mixed> $data
     */
    public function with(array $data): static
    {
        $clone = clone $this;
        $clone->merge($data);

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->serializeData($this->data),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create context from array representation.
     *
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): static
    {
        return new static(
            $array['data'] ?? [],
            $array['metadata'] ?? []
        );
    }

    /**
     * Serialize data for storage, converting models to identifiers.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function serializeData(array $data): array
    {
        $serialized = [];

        foreach ($data as $key => $value) {
            if ($value instanceof Model) {
                $serialized[$key] = [
                    '__type' => 'model',
                    '__class' => get_class($value),
                    '__key' => $value->getKey(),
                ];
            } elseif (is_array($value)) {
                $serialized[$key] = $this->serializeData($value);
            } else {
                $serialized[$key] = $value;
            }
        }

        return $serialized;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        Arr::forget($this->data, $offset);
    }
}
