<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Redis;

use DateInterval;
use DateTime;
use Predis\ClientInterface;
use Predis\Response\Status;
use Psr\SimpleCache\CacheInterface;
use Traversable;

use function array_fill_keys;
use function array_keys;
use function array_map;
use function count;
use function gettype;
use function is_iterable;
use function is_string;
use function iterator_to_array;
use function serialize;
use function strpbrk;
use function unserialize;

/**
 * RedisCache stores cache data in a Redis.
 *
 * Please refer to {@see CacheInterface} for common cache operations that are supported by RedisCache.
 */
final class RedisCache implements CacheInterface
{
    /**
     * @param ClientInterface $client Predis client instance to use.
     */
    public function __construct(private ClientInterface $client)
    {
    }

    public function get($key, $default = null)
    {
        $this->validateKey($key);
        $value = $this->client->get($key);
        return $value === null ? $default : unserialize($value);
    }

    public function set($key, $value, $ttl = null): bool
    {
        $ttl = $this->normalizeTtl($ttl);

        if ($this->isExpiredTtl($ttl)) {
            return $this->delete($key);
        }

        $this->validateKey($key);

        /** @var Status|null $result */
        $result = $this->isInfinityTtl($ttl)
            ? $this->client->set($key, serialize($value))
            : $this->client->set($key, serialize($value), 'EX', $ttl)
        ;

        return $result !== null;
    }

    public function delete($key): bool
    {
        return !$this->has($key) || $this->client->del($key) === 1;
    }

    public function clear(): bool
    {
        return $this->client->flushdb() !== null;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $keys = $this->iterableToArray($keys);
        $this->validateKeys($keys);
        /** @var string[] $keys */
        $values = array_fill_keys($keys, $default);
        /** @var null[]|string[] $valuesFromCache */
        $valuesFromCache = $this->client->mget($keys);

        $i = 0;
        /** @var mixed $default */
        foreach ($values as $key => $default) {
            /** @psalm-suppress MixedAssignment */
            $values[$key] = isset($valuesFromCache[$i]) ? unserialize($valuesFromCache[$i]) : $default;
            $i++;
        }

        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $values = $this->iterableToArray($values);
        $keys = array_map('\strval', array_keys($values));
        $this->validateKeys($keys);
        $ttl = $this->normalizeTtl($ttl);
        $serializeValues = [];

        if ($this->isExpiredTtl($ttl)) {
            return $this->deleteMultiple($keys);
        }

        /** @var mixed $value */
        foreach ($values as $key => $value) {
            $serializeValues[$key] = serialize($value);
        }

        if ($this->isInfinityTtl($ttl)) {
            $this->client->mset($serializeValues);
            return true;
        }

        $this->client->multi();
        $this->client->mset($serializeValues);

        foreach ($keys as $key) {
            $this->client->expire($key, $ttl);
        }

        $results = $this->client->exec();

        /** @var Status|null $result */
        foreach ((array) $results as $result) {
            if ($result === null) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple($keys): bool
    {
        $keys = $this->iterableToArray($keys);

        /** @psalm-suppress MixedAssignment, MixedArgument */
        foreach ($keys as $index => $key) {
            if (!$this->has($key)) {
                unset($keys[$index]);
            }
        }

        return empty($keys) || $this->client->del($keys) === count($keys);
    }

    public function has($key): bool
    {
        $this->validateKey($key);
        $ttl = $this->client->ttl($key);
        /** "-1" - if the key exists but has no associated expire {@see https://redis.io/commands/ttl}. */
        return $ttl > 0 || $ttl === -1;
    }

    /**
     * Normalizes cache TTL handling `null` value, strings and {@see DateInterval} objects.
     *
     * @param DateInterval|int|string|null $ttl The raw TTL.
     *
     * @return int TTL value as UNIX timestamp.
     */
    private function normalizeTtl($ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            return (new DateTime('@0'))
                ->add($ttl)
                ->getTimestamp();
        }

        return (int) $ttl;
    }

    /**
     * Converts iterable to array. If provided value is not iterable it throws an InvalidArgumentException.
     */
    private function iterableToArray(mixed $iterable): array
    {
        if (!is_iterable($iterable)) {
            throw new InvalidArgumentException('Iterable is expected, got ' . gettype($iterable));
        }

        /** @psalm-suppress RedundantCast */
        return $iterable instanceof Traversable ? iterator_to_array($iterable) : (array) $iterable;
    }

    private function validateKey(mixed $key): void
    {
        if (!is_string($key) || $key === '' || strpbrk($key, '{}()/\@:')) {
            throw new InvalidArgumentException('Invalid key value.');
        }
    }

    private function validateKeys(array $keys): void
    {
        if (empty($keys)) {
            throw new InvalidArgumentException('Invalid key values.');
        }

        /** @var mixed $key */
        foreach ($keys as $key) {
            $this->validateKey($key);
        }
    }

    private function isExpiredTtl(?int $ttl): bool
    {
        return $ttl !== null && $ttl <= 0;
    }

    private function isInfinityTtl(?int $ttl): bool
    {
        return $ttl === null;
    }
}
