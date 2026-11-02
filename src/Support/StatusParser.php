<?php

namespace Ahmed29345\Wathq\Support;

/**
 * Extracts the registration status from a Wathq response body.
 *
 * The shape depends on the subscription tier. The status endpoint may return
 * the status object on its own ({"id":1,"name":"Active"}), while other tiers
 * nest it under "status", "crStatus" or "registrationStatus", and may label
 * it with "name", "nameEn" or "nameAr" and identify it with "id" or "code".
 */
final class StatusParser
{
    /** Keys that may hold the status object, checked in order. */
    private const WRAPPER_KEYS = ['status', 'crStatus', 'registrationStatus'];

    private const ID_KEYS = ['id', 'code'];

    /** English first, so the display name is stable across tiers. */
    private const NAME_KEYS = ['nameEn', 'name', 'nameAr'];

    private const ACTIVE_ID = 1;

    private const ACTIVE_NAMES = ['active', 'نشط', 'قائم'];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{id: int|string|null, name: string|null, active: bool}
     */
    public static function parse(array $payload): array
    {
        $node = self::locateStatusNode($payload);

        if (is_string($node) || is_int($node)) {
            $node = is_int($node) || ctype_digit($node) ? ['id' => $node] : ['name' => $node];
        }

        $node = is_array($node) ? $node : [];

        $id = self::firstScalar($node, self::ID_KEYS);
        $names = self::allNames($node);

        return [
            'id' => $id,
            'name' => $names[0] ?? null,
            'active' => $id === self::ACTIVE_ID || self::anyNameIsActive($names),
        ];
    }

    /**
     * Wrapper keys win over top-level fields: richer tiers carry other
     * top-level data (for example the company name) that must not be
     * mistaken for the status.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private static function locateStatusNode(array $payload): mixed
    {
        foreach (self::WRAPPER_KEYS as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '' && $payload[$key] !== []) {
                return $payload[$key];
            }
        }

        foreach ([...self::ID_KEYS, ...self::NAME_KEYS] as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  list<string>  $keys
     */
    private static function firstScalar(array $node, array $keys): int|string|null
    {
        foreach ($keys as $key) {
            $value = $node[$key] ?? null;

            if (is_int($value)) {
                return $value;
            }

            if (is_string($value) && trim($value) !== '') {
                $value = trim($value);

                return ctype_digit($value) ? (int) $value : $value;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return list<string>
     */
    private static function allNames(array $node): array
    {
        $names = [];

        foreach (self::NAME_KEYS as $key) {
            if (is_string($node[$key] ?? null) && trim($node[$key]) !== '') {
                $names[] = trim($node[$key]);
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $names
     */
    private static function anyNameIsActive(array $names): bool
    {
        foreach ($names as $name) {
            if (in_array(mb_strtolower($name), self::ACTIVE_NAMES, true)) {
                return true;
            }
        }

        return false;
    }
}
