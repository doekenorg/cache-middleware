<?php

namespace DoekeNorg\CacheMiddleware;

final class MiddlewareHandler
{
    private \Traversable $stack;

    private string $method;

    private \Closure $final_action;

    public function __construct(iterable $stack, string $method, \Closure $final_action)
    {
        if (is_array($stack)) {
            $stack = new \ArrayIterator($stack);
        }

        $this->stack = $stack;
        $this->method = $method;
        $this->final_action = $final_action;

        // Make sure a reused iterator starts at the beginning.
        $this->stack->rewind();
    }

    public function handle($value)
    {
        // The last middleware (if any) has been called.
        if (!$this->stack->valid()) {
            return ($this->final_action)($value);
        }

        // Retrieve next middleware action
        $middleware = $this->stack->current();

        // Advance iterator for next call.
        $this->stack->next();

        return $middleware->{$this->method}($value, fn($value) => $this->handle($value));
    }
}
