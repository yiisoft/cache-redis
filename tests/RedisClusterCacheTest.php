<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Redis\Tests;

use ArrayIterator;
use DateInterval;
use Exception;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Predis\Client;
use ReflectionException;
use ReflectionObject;
use stdClass;
use Yiisoft\Cache\Redis\RedisCache;

use function array_keys;
use function array_map;
use function gettype;
use function is_array;
use function is_object;
use function time;

/**
 * Tests for Redis cluster instance
 */
final class RedisClusterCacheTest extends TestCase
{
    private RedisCache $cache;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new RedisCache(new Client(
            [
                ['host' => 'redis1', 'port' => 6381,],
                ['host' => 'redis2', 'port' => 6382,],
                ['host' => 'redis3', 'port' => 6383,],
                ['host' => 'redis4', 'port' => 6384,],
                ['host' => 'redis5', 'port' => 6385,],
                ['host' => 'redis6', 'port' => 6386,],
            ],
            [
                'cluster' => 'redis',
                'parameters' => [
                    'password' => 'Password',
                ],
                'prefix' => 'yiitest',
            ],
        ));
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cache->clear();

        parent::tearDown();
    }

    /**
     * @return array
     */
    public function dataProvider(): array
    {
        $object = new stdClass();
        $object->test_field = 'test_value';

        return [
            'integer' => ['test_integer', 1],
            'double' => ['test_double', 1.1],
            'string' => ['test_string', 'a'],
            'boolean_true' => ['test_boolean_true', true],
            'boolean_false' => ['test_boolean_false', false],
            'object' => ['test_object', $object],
            'array' => ['test_array', ['test_key' => 'test_value']],
            'null' => ['test_null', null],
            'supported_key_characters' => ['AZaz09_.', 'b'],
            '64_characters_key_max' => ['bVGEIeslJXtDPrtK.hgo6HL25_.1BGmzo4VA25YKHveHh7v9tUP8r5BNCyLhx4zy', 'c'],
            'string_with_number_key' => ['111', 11],
            'string_with_number_key_1' => ['022', 22],
        ];
    }

    /**
     * Check redis cluster connection
     * @return void
     */
    public function testIsCLuster(): void
    {
        $this->assertTrue($this->cache->isCluster());
    }

    /**
     * @dataProvider dataProvider
     *
     * @param string $key
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    public function testSet(string $key, mixed $value): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue($this->cache->set($key, $value));
        }
    }

    /**
     * @dataProvider dataProvider
     *
     * @param string $key
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    public function testSetWithTtl(string $key, mixed $value): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue($this->cache->set($key, $value, 3600));
        }
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testGet($key, $value): void
    {
        $this->cache->set($key, $value);
        $valueFromCache = $this->cache->get($key, 'default');

        $this->assertSameExceptObject($value, $valueFromCache);
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testValueInCacheCannotBeChanged($key, $value): void
    {
        $this->cache->set($key, $value);
        $valueFromCache = $this->cache->get($key, 'default');

        $this->assertSameExceptObject($value, $valueFromCache);

        if (is_object($value)) {
            $originalValue = clone $value;
            $valueFromCache->test_field = 'changed';
            $value->test_field = 'changed';
            $valueFromCacheNew = $this->cache->get($key, 'default');
            $this->assertSameExceptObject($originalValue, $valueFromCacheNew);
        }
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testHas($key, $value): void
    {
        $this->cache->set($key, $value);

        $this->assertTrue($this->cache->has($key));
        // check whether exists affects the value
        $this->assertSameExceptObject($value, $this->cache->get($key));

        $this->assertTrue($this->cache->has($key));
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testDeleteAndHasAndGetNonExistent(): void
    {
        $this->assertTrue($this->cache->delete('non-existent-key'));
        $this->assertFalse($this->cache->has('non-existent-key'));
        $this->assertNull($this->cache->get('non-existent-key'));
        $this->assertTrue($this->cache->deleteMultiple(['non-existent-key']));
        $this->assertSame(['non-existent-key' => null], $this->cache->getMultiple(['non-existent-key']));
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testDelete($key, $value): void
    {
        $this->cache->set($key, $value);

        $this->assertSameExceptObject($value, $this->cache->get($key));
        $this->assertTrue($this->cache->delete($key));
        $this->assertNull($this->cache->get($key));
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testClear($key, $value): void
    {
        foreach ($this->dataProvider() as $data) {
            $this->cache->set($data[0], $data[1]);
        }

        $this->assertTrue($this->cache->clear());
        $this->assertNull($this->cache->get($key));
    }

    /**
     * @return array
     */
    public function dataProviderSetMultiple(): array
    {
        return [
            [null],
            [2],
        ];
    }

    /**
     * Data provider for {@see testNormalizeTtl()}
     *
     * @throws Exception
     *
     * @return array test data
     */
    public function dataProviderNormalizeTtl(): array
    {
        return [
            [123, 123],
            ['123', 123],
            ['', 0], // expired
            [null, null], // infinity
            [0, 0], // expired
            [new DateInterval('PT6H8M'), 6 * 3600 + 8 * 60],
            [new DateInterval('P2Y4D'), 2 * 365 * 24 * 3600 + 4 * 24 * 3600],
        ];
    }

    /**
     * @dataProvider dataProviderNormalizeTtl
     *
     * @param mixed $ttl
     * @param mixed $expectedResult
     *
     * @throws ReflectionException
     */
    public function testNormalizeTtl($ttl, $expectedResult): void
    {
        $reflection = new ReflectionObject($this->cache);
        $method = $reflection->getMethod('normalizeTtl');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->cache, [$ttl]);
        $method->setAccessible(false);

        $this->assertSameExceptObject($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function iterableProvider(): array
    {
        return [
            'array' => [
                ['a' => 1, 'b' => 2,],
                ['a' => 1, 'b' => 2,],
            ],
            'ArrayIterator' => [
                ['a' => 1, 'b' => 2,],
                new ArrayIterator(['a' => 1, 'b' => 2,]),
            ],
            'IteratorAggregate' => [
                ['a' => 1, 'b' => 2,],
                new class () implements IteratorAggregate {
                    public function getIterator(): ArrayIterator
                    {
                        return new ArrayIterator(['a' => 1, 'b' => 2,]);
                    }
                },
            ],
            'generator' => [
                ['a' => 1, 'b' => 2,],
                (static function () {
                    yield 'a' => 1;
                    yield 'b' => 2;
                })(),
            ],
        ];
    }

    /**
     * @return array
     */
    public function invalidKeyProvider(): array
    {
        return [
            'psr-reserved' => ['{}()/\@:'],
            'empty-string' => [''],
        ];
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testGetThrowExceptionForInvalidKey($key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get($key);
    }

    /**
     * @param array $values
     * @return array
     */
    private function prepareKeysOfValues(array $values): array
    {
        return array_map('\strval', array_keys($values));
    }

    /**
     * @return array
     */
    private function getDataProviderData(): array
    {
        $dataProvider = $this->dataProvider();
        $data = [];

        foreach ($dataProvider as $item) {
            $data[$item[0]] = $item[1];
        }

        return $data;
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     * @return void
     */
    private function assertSameExceptObject(mixed $expected, mixed $actual): void
    {
        // Assert for all types.
        $this->assertEquals($expected, $actual);

        // No more asserts for objects.
        if (is_object($expected)) {
            return;
        }

        // Assert same for all types except objects and arrays that can contain objects.
        if (!is_array($expected)) {
            $this->assertSame($expected, $actual);
            return;
        }

        // Assert same for each element of the array except objects.
        foreach ($expected as $key => $value) {
            if (is_object($value)) {
                $this->assertEquals($value, $actual[$key]);
            } else {
                $this->assertSame($value, $actual[$key]);
            }
        }
    }
}
