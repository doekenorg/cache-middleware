<?php

namespace DoekeNorg\Psr6Middleware;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class MiddlewareDecorator implements CacheItemPoolInterface
{
    private static array $interfaces = [
        MiddlewareGetInterface::class,
        MiddlewareSaveInterface::class,
        MiddlewareDeleteInterface::class,
    ];

    /**
     * Array of middleware stacks.
     * @var \SplStack[]
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
        foreach (self::$interfaces as $interface) {
            $this->middleware[$interface] = new \SplStack();
        }

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
        $action = fn($item) => $this->pool->save($item);

        foreach ($this->getMiddlewares(MiddlewareSaveInterface::class) as $middleware) {
            $action = fn($item) => $middleware->processSave($item, $action);
        }

        return $action($item);
    }

    public function getItem($key)
    {
        $action = fn(string $key) => $this->pool->getItem($key);

        foreach ($this->getMiddlewares(MiddlewareGetInterface::class) as $middleware) {
            $action = fn(string $key) => $middleware->processGet($key, $action);
        }

        return $action($key);
    }

    public function getItems(array $keys = [])
    {
        // Todo, should work; but ideally it should somehow use parent::getItems for the values.
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }

        return $result;
    }

    public function deleteItem($key)
    {
        $action = fn(string $key) => $this->pool->deleteItem($key);

        foreach ($this->getMiddlewares(MiddlewareDeleteInterface::class) as $middleware) {
            $action = fn(string $key) => $middleware->processDelete($key, $action);
        }

        return $action($key);
    }

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
        $action = fn($item) => $this->pool->saveDeferred($item);

        foreach ($this->getMiddlewares(MiddlewareSaveInterface::class) as $middleware) {
            $action = fn($item) => $middleware->processSave($item, $action);
        }

        return $action($item);
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
     * @return \SplStack|T[]
     */
    private function getMiddlewares(string $interface): \SplStack
    {
        return $this->middleware[$interface];
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
