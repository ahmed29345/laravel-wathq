<?php

namespace Ahmed29345\Wathq;

use Ahmed29345\Wathq\Support\StatusParser;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The outcome of a commercial registration lookup.
 *
 * @implements Arrayable<string, mixed>
 */
final class CrVerification implements Arrayable, JsonSerializable
{
    /**
     * @param  array<array-key, mixed>  $raw  The decoded response body, untouched.
     * @param  int|null  $httpStatus  Null when the result did not come from an HTTP call.
     * @param  bool  $simulated  True for configured test CR numbers.
     */
    public function __construct(
        public readonly string $crNumber,
        public readonly bool $found,
        public readonly bool $active,
        public readonly int|string|null $statusId,
        public readonly ?string $statusName,
        public readonly array $raw,
        public readonly ?int $httpStatus,
        public readonly bool $simulated = false,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromPayload(string $crNumber, array $payload, int $httpStatus): self
    {
        $status = StatusParser::parse($payload);

        return new self(
            crNumber: $crNumber,
            found: true,
            active: $status['active'],
            statusId: $status['id'],
            statusName: $status['name'],
            raw: $payload,
            httpStatus: $httpStatus,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function notFound(string $crNumber, array $payload = [], int $httpStatus = 404): self
    {
        return new self($crNumber, false, false, null, null, $payload, $httpStatus);
    }

    public static function simulatedActive(string $crNumber): self
    {
        return new self($crNumber, true, true, 1, 'Active', [], null, simulated: true);
    }

    /**
     * Rebuild an instance from {@see toArray()} output (used by the cache).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            crNumber: (string) $data['cr_number'],
            found: (bool) $data['found'],
            active: (bool) $data['active'],
            statusId: $data['status_id'] ?? null,
            statusName: $data['status_name'] ?? null,
            raw: (array) ($data['raw'] ?? []),
            httpStatus: isset($data['http_status']) ? (int) $data['http_status'] : null,
            simulated: (bool) ($data['simulated'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cr_number' => $this->crNumber,
            'found' => $this->found,
            'active' => $this->active,
            'status_id' => $this->statusId,
            'status_name' => $this->statusName,
            'http_status' => $this->httpStatus,
            'simulated' => $this->simulated,
            'raw' => $this->raw,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
