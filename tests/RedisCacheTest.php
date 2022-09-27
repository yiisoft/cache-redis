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

final class RedisCacheTest extends TestCase
{
    private RedisCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new RedisCache(new Client([
            'host' => '127.0.0.1',
            'port' => 6379,
            'prefix' => 'yiitest',
        ]));
    }

    protected function tearDown(): void
    {
        $this->cache->clear();

        parent::tearDown();
    }

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
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testSet($key, $value): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue($this->cache->set($key, $value));
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
    public function testSetWithTtl($key, $value): void
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

    public function dataProviderSetMultiple(): array
    {
        return [
            [null],
            [2],
        ];
    }

    /**
     * @dataProvider dataProviderSetMultiple
     *
     *
     * @throws InvalidArgumentException
     */
    public function testSetMultiple(?int $ttl): void
    {
        $data = $this->getDataProviderData();
        $this->cache->setMultiple($data, $ttl);

        $reflection = new ReflectionObject($this->cache);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $client = $property->getValue($this->cache);
        $property->setAccessible(false);

        foreach ($data as $key => $value) {
            $this->assertSameExceptObject($value, $this->cache->get((string) $key));
            $this->assertSame($ttl ?? -1, $client->ttl($key));
        }
    }

    /**
     * @dataProvider dataProviderSetMultiple
     *
     *
     * @throws InvalidArgumentException
     */
    public function testReSetMultiple(?int $ttl): void
    {
        $data = $this->getDataProviderData();

        $this->assertTrue($this->cache->setMultiple($data, $ttl));

        foreach ($data as $key => $value) {
            $this->assertSameExceptObject($value, $this->cache->get((string) $key));
        }

        $this->assertTrue($this->cache->setMultiple($data, $ttl));

        foreach ($data as $key => $value) {
            $this->assertSameExceptObject($value, $this->cache->get((string) $key));
        }
    }

    public function testSetMultipleFailure(): void
    {
        $client = $this
            ->getMockBuilder(ClientInterface::class)
            ->addMethods(['exec'])
            ->getMockForAbstractClass()
        ;

        $client
            ->method('exec')
            ->willReturn([null]);

        $reflection = new ReflectionObject($this->cache);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->cache, $client);
        $property->setAccessible(false);

        $this->assertFalse($this->cache->setMultiple(['key' => 'value'], time()));
    }

    public function testGetMultiple(): void
    {
        $data = $this->getDataProviderData();
        $keys = $this->prepareKeysOfValues($data);
        $this->cache->setMultiple($data);

        $this->assertSameExceptObject($data, $this->cache->getMultiple($keys));
    }

    public function testDeleteMultiple(): void
    {
        $data = $this->getDataProviderData();
        $keys = $this->prepareKeysOfValues($data);
        $this->cache->setMultiple($data);

        $this->assertSameExceptObject($data, $this->cache->getMultiple($keys));
        $this->assertTrue($this->cache->deleteMultiple($keys));

        $emptyData = array_map(static fn () => null, $data);

        $this->assertSameExceptObject($emptyData, $this->cache->getMultiple($keys));
    }

    public function testZeroAndNegativeTtl(): void
    {
        $this->cache->set('a', 1, -1);
        $this->assertFalse($this->cache->has('a'));

        $this->cache->set('b', 2, 0);
        $this->assertFalse($this->cache->has('b'));

        $this->cache->setMultiple(['a' => 1, 'b' => 2], -1);
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));

        $this->cache->setMultiple(['a' => 1, 'b' => 2], 0);
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
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
     *
     * @throws ReflectionException
     */
    public function testNormalizeTtl(mixed $ttl, mixed $expectedResult): void
    {
        $reflection = new ReflectionObject($this->cache);
        $method = $reflection->getMethod('normalizeTtl');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->cache, [$ttl]);
        $method->setAccessible(false);

        $this->assertSameExceptObject($expectedResult, $result);
    }

    public function iterableProvider(): array
    {
        return [
            'array' => [
                ['a' => 1, 'b' => 2,],
                ['a' => 1, 'b' => 2,],
            ],
            \ArrayIterator::class => [
                ['a' => 1, 'b' => 2,],
                new ArrayIterator(['a' => 1, 'b' => 2,]),
            ],
            \IteratorAggregate::class => [
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
     * @dataProvider iterableProvider
     *
     *
     * @throws InvalidArgumentException
     */
    public function testValuesAsIterable(array $array, iterable $iterable): void
    {
        $this->cache->setMultiple($iterable);

        $this->assertSameExceptObject($array, $this->cache->getMultiple(array_keys($array)));
    }

    public function invalidKeyProvider(): array
    {
        return [
            'int' => [1],
            'float' => [1.1],
            'null' => [null],
            'bool' => [true],
            'object' => [new stdClass()],
            'callable' => [fn () => 'key'],
            'psr-reserved' => ['{}()/\@:'],
            'empty-string' => [''],
        ];
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testGetThrowExceptionForInvalidKey(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testSetThrowExceptionForInvalidKey(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set($key, 'value');
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testDeleteThrowExceptionForInvalidKey(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->delete($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testGetMultipleThrowExceptionForInvalidKeys(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->getMultiple([$key]);
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testGetMultipleThrowExceptionForInvalidKeysNotIterable(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Iterable is expected, got ' . gettype($key));
        $this->cache->getMultiple($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testSetMultipleThrowExceptionForInvalidKeysNotIterable(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Iterable is expected, got ' . gettype($key));
        $this->cache->setMultiple($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testDeleteMultipleThrowExceptionForInvalidKeys(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->deleteMultiple([$key]);
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testDeleteMultipleThrowExceptionForInvalidKeysNotIterable(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Iterable is expected, got ' . gettype($key));
        $this->cache->deleteMultiple($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testHasThrowExceptionForInvalidKey(mixed $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->has($key);
    }

    public function testGetMultipleThrowExceptionForEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->getMultiple([]);
    }

    public function testSetMultipleThrowExceptionForEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->setMultiple([]);
    }

    private function prepareKeysOfValues(array $values): array
    {
        return array_map('\strval', array_keys($values));
    }

    private function getDataProviderData(): array
    {
        $dataProvider = $this->dataProvider();
        $data = [];

        foreach ($dataProvider as $item) {
            $data[$item[0]] = $item[1];
        }

        return $data;
    }

    private function assertSameExceptObject($expected, $actual): void
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
