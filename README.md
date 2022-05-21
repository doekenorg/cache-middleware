# PSR-6 Cache Middleware

This is an experimental package that provides middleware for any [PSR-6 cache pool](https://www.php-fig.org/psr/psr-6/).

## Usage

To apply middlewares you decorate your PSR-6 cache pool with the `DoekeNorg\CacheMiddleware\MiddlewareDecorator`. The
constructor of the decorator receives the decorated cache pool, as well as an optional list of middleware classes. 
You can also add more middlewares by calling the `addMiddleware(...$middlewares)` method on the decorator.

```php
use DoekeNorg\CacheMiddleware\MiddlewareCachePool;

$regular_psr6_cache_pool = ...; // your current PSR-6 cache pool
$decorator_cache_pool = new MiddlewareCachePool($regular_psr6_cache_pool, ...$middlewares);
//or
$decorator_cache_pool = new MiddlewareCachePool($regular_psr6_cache_pool);
$decorator_cache_pool->addMiddleware(...$middlewares);
```

## Types of middleware

This package provides three middleware interfaces:

- `MiddlewareGetInterface`: This middleware is called in combination with `->getItem()` and `->getItems()`
- `MiddlewareSaveInterface`: This middleware is called in combination with `->save()` and `->safeDefered()`
- `MiddlewareDeleteInterface`: This middleware is called in combination with `->deleteItem()` and `->deleteItems()`

## Implementing the interfaces

Every interface has a single `process<Type>` method that gets called in the middleware process. So a single middleware
class can implement multiple middleware interfaces, and provide a common goal.

```php
use DoekeNorg\CacheMiddleware\MiddlewareGetInterface;
use DoekeNorg\CacheMiddleware\MiddlewareSaveInterface;
use DoekeNorg\CacheMiddleware\MiddlewareDeleteInterface;
use Psr\Cache\CacheItemInterface;

final class ExampleMiddleware implements MiddlewareGetInterface, MiddlewareSaveInterface, MiddlewareDeleteInterface {
    public function processGet(string $key, callable $next): CacheItemInterface {
        $cache_item = $next($key);
        // do something to the cache item
        return $cache_item;
    }
    
    public function processSave(CacheItemInterface $cacheItem, callable $next): bool {
        // do something to the cache item
        return $next($cacheItem);
    }
    
    public function processDelete(string $key, callable $next): bool {
        // perform some action based on the key or the deleted result.
        return $next($key);
    }
}
```

## Notes

This package is a fun experiment to see if the middleware pattern can be useful around a PSR-6 cache. The package is
under development, and the implementation can change during the non-stable phase. As long as this is a `0.*.*` release
consider every *minor* change as a *major* update.

**Warning:** While it is possible to change the cache keys of the methods I do not recommend this in the middleware. 
Use it to update the cache item (adding tags, deleting other items for a given key, etc.).

**Caveat:** To trigger the middlewares on all items in combination with `getItems()` and `deleteItems()` these functions
do *NOT* call these functions on the decorated cache pool, but rather call `getItem()` and `deleteItem()` multiple times
on the decorator. This technically makes this decorator a proxy, but practically it still is a decorator. Just note that
if you need the inner methods called, this package probably isn't for you, and you should use a custom decorator.

## Changelog

Please see the [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
