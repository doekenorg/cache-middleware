<?php

namespace DoekeNorg\Psr6Middleware;

use Psr\Cache\CacheItemInterface;

interface MiddlewareSaveInterface
{
    /**
     * @param CacheItemInterface $cacheItem The cache item to save.
     * @param callable $next The middleware handler.
     * @return bool Whether the item was successfully persisted.
     */
    public function processSave(CacheItemInterface $cacheItem, callable $next): bool;
}
