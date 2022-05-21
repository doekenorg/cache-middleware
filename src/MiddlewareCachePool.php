<?php

namespace DoekeNorg\CacheMiddleware;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class MiddlewareCachePool implements CacheItemPoolInterface
{
    private static array $interfaces = [
        MiddlewareGetInterface::class,
        MiddlewareSaveInterface::class,
        MiddlewareDeleteInterface::class,
    ];

    /**
     * Array of middleware stacks.
     * @var array
     */
    private array $middleware;

    /**
     * The decorated cache item pool.
     * @var CacheItemPoolInterface
     */
    private CacheItemPoolInterface $pool;

    public function __construct(CacheItemPoolInterface $pool, iterable $middleware = [])
    {
        $this->pool = $pool;
        $this->addMiddleware(...$middleware);
    }

    public function addMiddleware(...$middleware): self
    {
        foreach ($middleware as $item) {
            $has_interface = false;
            foreach (self::$interfaces as $interface) {
                if ($item instanceof $interface) {
                    $this->middleware[$interface][] = $item;

                    $has_interface = true;
                }
            }

            if (!$has_interface) {
                throw new \InvalidArgumentException(sprintf(
                    'Middleware "%s" should implement one of the middleware interfaces.',
                    is_object($item) ? get_class($item) : (string)$item,
                ));
            }
        }

        return $this;
    }

    public function save(CacheItemInterface $item)
    {
        return (new MiddlewareHandler(
            $this->getMiddlewares(MiddlewareSaveInterface::class),
            'processSave',
            fn(CacheItemInterface $item) => $this->pool->save($item)
        ))->handle($item);
    }

    public function getItem($key)
    {
        return (new MiddlewareHandler(
            $this->getMiddlewares(MiddlewareGetInterface::class),
            'processGet',
            fn(string $key) => $this->pool->getItem($key),
        ))->handle($key);
    }

    /**
     * @inheritDoc
     *
     * Note: does not call the inner `getItems()`.
     */
    public function getItems(array $keys = [])
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }

        return $result;
    }

    public function deleteItem($key)
    {
        return (new MiddlewareHandler(
            $this->getMiddlewares(MiddlewareDeleteInterface::class),
            'processDelete',
            fn(string $key) => $this->pool->deleteItem($key),
        ))->handle($key);
    }

    /**
     * @inheritDoc
     *
     * Note: does not call the inner `deleteItems()`.
     */
    public function deleteItems(array $keys)
    {
        $result = true;
        foreach ($keys as $key) {
            if (!$this->deleteItem($key)) {
                $result = false;
            }
        }

        return $result;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        return (new MiddlewareHandler(
            $this->getMiddlewares(MiddlewareSaveInterface::class),
            'processSave',
            fn(CacheItemInterface $item) => $this->pool->saveDeferred($item),
        ))->handle($item);
    }

    public function hasItem($key)
    {
        return $this->pool->hasItem($key);
    }

    public function clear()
    {
        return $this->pool->clear();
    }

    public function commit()
    {
        return $this->pool->commit();
    }

    /**
     * @template T
     * @param class-string<T> $interface
     * @return T[]
     */
    private function getMiddlewares(string $interface): array
    {
        return $this->middleware[$interface] ?? [];
    }

    public function __call($name, $arguments)
    {
        return $this->pool->{$name}(...$arguments);
    }

    public function __get($name)
    {
        return $this->pool->{$name};
    }

    public function __isset($name)
    {
        return isset($this->pool->{$name});
    }
}
