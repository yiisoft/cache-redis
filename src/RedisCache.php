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
     * @var ClientInterface $client Predis client instance to use.
     */
    private ClientInterface $client;

    /**
     * @param ClientInterface $client Predis client instance to use.
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        $value = $this->client->get($key);
        return $value === null ? $default : unserialize($value);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|DateInterval|null $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
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

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function delete(string $key): bool
    {
        return !$this->has($key) || $this->client->del($key) === 1;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        return $this->client->flushdb() !== null;
    }

    /**
     * @param iterable $keys
     * @param mixed|null $default
     * @return iterable
     * @throws InvalidArgumentException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        /** @var string[] $keys */
        $keys = $this->iterableToArray($keys);
        $this->validateKeys($keys);
        $values = array_fill_keys($keys, $default);
        /** @var null[]|string[] $valuesFromCache */
        $valuesFromCache = $this->client->mget($keys);

        $i = 0;

        /** @psalm-suppress MixedAssignment */
        foreach ($values as $key => $value) {
            $values[$key] = isset($valuesFromCache[$i]) ? unserialize($valuesFromCache[$i]) : $value;
            $i++;
        }

        return $values;
    }

    /**
     * @param iterable $values
     * @param int|DateInterval|null $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
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
        if (in_array(null, (array)$results, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param iterable $keys
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteMultiple(iterable $keys): bool
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

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function has(string $key): bool
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
     * @return int|null TTL value as UNIX timestamp.
     */
    private function normalizeTtl(null|int|string|DateInterval $ttl): ?int
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
     * Converts iterable to array.
     *
     * @param iterable $iterable
     *
     * @return array
     */
    private function iterableToArray(iterable $iterable): array
    {
        /** @psalm-suppress RedundantCast */
        return $iterable instanceof Traversable ? iterator_to_array($iterable) : (array) $iterable;
    }

    /**
     * @param string $key
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateKey(string $key): void
    {
        if ($key === '' || strpbrk($key, '{}()/\@:')) {
            throw new InvalidArgumentException('Invalid key value.');
        }
    }

    /**
     * @param string[] $keys
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateKeys(array $keys): void
    {
        if ([] === $keys) {
            throw new InvalidArgumentException('Invalid key values.');
        }

        foreach ($keys as $key) {
            $this->validateKey($key);
        }
    }

    /**
     * @param int|null $ttl
     * @return bool
     */
    private function isExpiredTtl(?int $ttl): bool
    {
        return $ttl !== null && $ttl <= 0;
    }

    /**
     * @param int|null $ttl
     * @return bool
     */
    private function isInfinityTtl(?int $ttl): bool
    {
        return $ttl === null;
    }
}
