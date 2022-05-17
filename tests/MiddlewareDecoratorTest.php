<?php

namespace DoekeNorg\Psr6Middleware\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use DoekeNorg\Psr6Middleware\MiddlewareDecorator;
use DoekeNorg\Psr6Middleware\MiddlewareDeleteInterface;
use DoekeNorg\Psr6Middleware\MiddlewareGetInterface;
use DoekeNorg\Psr6Middleware\MiddlewareSaveInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Unit tests for {@see MiddlewareDecorator}
 */
final class MiddlewareDecoratorTest extends TestCase
{
    private TestArrayCachePool $array_pool;
    private MiddlewareDecorator $decorator;

    protected function setUp(): void
    {
        $this->array_pool = new TestArrayCachePool();
        $this->decorator = new MiddlewareDecorator($this->array_pool);
    }

    public function testGetItem(): void
    {
        $item = $this->array_pool->getItem('test-one-two');
        $item->set('test');
        $this->array_pool->save($item);

        $this->decorator->addMiddleware(
            new TestMiddlewareSave('one', 'get'),
            new TestMiddlewareSave('two', 'get'),
        );

        self::assertSame(
            'one-two-test', // updated value
            $this->decorator->getItem('test')->get(), // key is updated to `test-one-two` by middlewares
        );
    }

    public function testGetItems(): void
    {
        $items = $this->array_pool->getItems(['first-one-two', 'second-one-two']);
        foreach ($items as $key => $item) {
            $value = explode('-', $key);
            $item->set($value[0]);
            $this->array_pool->save($item);
        }

        $this->decorator->addMiddleware(
            new TestMiddlewareSave('one', 'get'),
            new TestMiddlewareSave('two', 'get'),
        );

        $result = array_map(
            fn(CacheItemInterface $item) => $item->get(),
            $this->decorator->getItems(['first', 'second']),
        );

        self::assertSame([
            'first' => 'one-two-first',
            'second' => 'one-two-second',
        ], $result);

    }

    public function testSave(): void
    {
        $this->decorator->addMiddleware(
            new TestMiddlewareSave('one', 'save'),
            new TestMiddlewareSave('two', 'save'),
        );

        $item = $this->decorator->getItem('item');
        $item->set('test');

        $this->decorator->save($item);

        self::assertSame('two-one-test', $this->array_pool->getItem('item')->get());
    }

    public function testSaveDeferred(): void
    {
        $this->decorator->addMiddleware(
            new TestMiddlewareSave('one', 'save'),
            new TestMiddlewareSave('two', 'save'),
        );
        $item = $this->decorator->getItem('item');
        $item->set('test');

        $this->decorator->saveDeferred($item);
        self::assertTrue($this->array_pool->hasItem('item'));
        self::assertSame('two-one-test', $this->array_pool->getItem('item')->get());
    }

    public function testDeleteItem(): void
    {
        $cache_mock = $this->createMock(CacheItemPoolInterface::class);
        $decorator = new MiddlewareDecorator($cache_mock, [
            new TestMiddlewareSave('one', 'delete'),
            new TestMiddlewareSave('two', 'delete'),
        ]);

        $cache_mock
            ->expects(self::once())
            ->method('deleteItem')
            ->with('test-one-two')
            ->willReturn(false);

        self::assertFalse($decorator->deleteItem('test'));
    }

    public function testDeleteItems(): void
    {
        $cache_mock = $this->createMock(CacheItemPoolInterface::class);
        $decorator = new MiddlewareDecorator($cache_mock, [
            new TestMiddlewareSave('one', 'delete'),
            new TestMiddlewareSave('two', 'delete'),
        ]);

        $cache_mock
            ->expects(self::exactly(2))
            ->method('deleteItem')
            ->withConsecutive(['true-one-two'], ['false-one-two'])
            ->willReturnOnConsecutiveCalls(true, false);

        self::assertFalse($decorator->deleteItems(['true', 'false']));
    }

    public function testHasItem(): void
    {
        $decorator = new MiddlewareDecorator($this->array_pool);
        $item = $decorator->getItem('test');
        self::assertFalse($decorator->hasItem('test'));
        $decorator->save($item);
        self::assertTrue($decorator->hasItem('test'));
    }

    public function testClear(): void
    {
        $decorator = new MiddlewareDecorator($this->array_pool);
        $decorator->save($decorator->getItem('test'));
        self::assertTrue($decorator->hasItem('test'));
        $decorator->clear();
        self::assertFalse($decorator->hasItem('test'));
    }

    public function testCommit(): void
    {
        $decorator = new MiddlewareDecorator($this->array_pool);
        $decorator->saveDeferred($decorator->getItem('test'));
        self::assertFalse($decorator->hasItem('test'));
        self::assertTrue($decorator->commit());
        self::assertTrue($decorator->hasItem('test'));
    }

    public function testAddMiddlewareInvalidValue(): void
    {
        $this->expectExceptionMessage('Middleware "invalid" should implement one of the middleware interfaces.');
        $this->decorator->addMiddleware('invalid');
    }

    public function testAddMiddlewareInvalidObject(): void
    {
        $this->expectExceptionMessage('Middleware "stdClass" should implement one of the middleware interfaces.');
        $this->decorator->addMiddleware((object)[]);
    }

    public function testMagicMethods(): void
    {
        self::assertTrue(isset($this->decorator->public_param));
        self::assertSame('public value', $this->decorator->public_param);
        self::assertSame('public function value test', $this->decorator->publicFunction('test'));
    }
}

final class TestArrayCachePool extends ArrayCachePool
{
    public string $public_param = 'public value';

    public function publicFunction(string $value): string
    {
        return 'public function value ' . $value;
    }
}

final class TestMiddlewareSave implements MiddlewareSaveInterface, MiddlewareGetInterface, MiddlewareDeleteInterface
{
    private string $value;
    private string $type;

    public function __construct(string $value, string $type)
    {
        $this->value = $value;
        $this->type = $type;
    }

    public function processSave(CacheItemInterface $cacheItem, callable $next): bool
    {
        if ($this->type === 'save') {
            $cacheItem->set($this->value . '-' . $cacheItem->get());
        }

        return $next($cacheItem);
    }

    public function processGet(string $key, callable $next): CacheItemInterface
    {
        if ($this->type !== 'get') {
            return $next($key);
        }

        /** @var CacheItemInterface $item */
        $item = $next($key . '-' . $this->value);

        return $item->set($this->value . '-' . $item->get());
    }

    public function processDelete(string $key, callable $next): bool
    {
        if ($this->type !== 'delete') {
            return $next($key);
        }

        return $next($key . '-' . $this->value);
    }
}
