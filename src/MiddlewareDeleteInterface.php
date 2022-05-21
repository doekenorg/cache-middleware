<?php

namespace DoekeNorg\CacheMiddleware;

use Psr\Cache\InvalidArgumentException;

interface MiddlewareDeleteInterface
{
    /**
     * @param string $key The key for which to return the corresponding cache item.
     * @param callable $next The middleware handler.
     * @return bool Whether the cache item was successfully removed. Returns `false` if there was an error.
     * @throws InvalidArgumentException If the key is not a legal value.
     */
    public function processDelete(string $key, callable $next): bool;
}
