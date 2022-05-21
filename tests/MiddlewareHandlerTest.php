<?php

namespace DoekeNorg\CacheMiddleware\Tests;

use DoekeNorg\CacheMiddleware\MiddlewareHandler;
use PHPUnit\Framework\TestCase;

final class MiddlewareHandlerTest extends TestCase
{
    public function testHandleEmptyStack(): void
    {
        $handler = new MiddlewareHandler([], '', fn($value) => $value . '-suffix');
        self::assertSame('value-suffix', $handler->handle('value'));
    }

    public function testHandleSingleMiddleware(): void
    {
        $handler = new MiddlewareHandler([
            new TestMiddleware('one'),
        ], 'handle', fn($value) => $value . '-suffix');

        self::assertSame('one-value-suffix', $handler->handle('value'));
    }

    public function testHandleMultipleMiddlewares(): void
    {
        $handler = new MiddlewareHandler([
            new TestMiddleware('one'),
            new TestMiddleware('two'),
            new TestMiddleware('three'),
        ], 'handle', fn($value) => $value . '-suffix');

        self::assertSame('one-two-three-value-suffix', $handler->handle('value'));
    }
}

final class TestMiddleware
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function handle($value, $next)
    {
        $result = $this->value . '-' . $next($value);

        return $result;
    }
}
