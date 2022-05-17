<?php

namespace DoekeNorg\Psr6Middleware;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;

interface MiddlewareGetInterface
{
    /**
     * @param string $key The key for which to return the corresponding cache item.
     * @param callable $next The middleware handler.
     * @return CacheItemInterface The corresponding cache item.
     * @throws InvalidArgumentException If the key is not a legal value.
     */
    public function processGet(string $key, callable $next): CacheItemInterface;
}
