<?php

namespace Spatie\Async\Process;

use ReflectionException;
use ReflectionFunction;
use Throwable;

trait ProcessCallbacks
{
    /** @var array */
    protected array $successCallbacks = [];

    /** @var array */
    protected array $errorCallbacks = [];

    /** @var array */
    protected array $timeoutCallbacks = [];

    /**
     * @param callable $callback
     * @return ProcessCallbacks|ParallelProcess|SynchronousProcess
     */
    public function then(callable $callback): self
    {
        $this->successCallbacks[] = $callback;

        return $this;
    }

    /**
     * @param callable $callback
     * @return ProcessCallbacks|ParallelProcess|SynchronousProcess
     */
    public function catch(callable $callback): self
    {
        $this->errorCallbacks[] = $callback;

        return $this;
    }

    /**
     * @param callable $callback
     * @return ProcessCallbacks|ParallelProcess|SynchronousProcess
     */
    public function timeout(callable $callback): self
    {
        $this->timeoutCallbacks[] = $callback;

        return $this;
    }

    /**
     * @return mixed|void
     * @throws Throwable
     */
    public function triggerSuccess()
    {
        if ($this->getErrorOutput()) {
            $this->triggerError();

            return;
        }

        $output = $this->getOutput();

        foreach ($this->successCallbacks as $callback) {
            $callback($output);
        }

        return $output;
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function triggerError(): void
    {
        $exception = $this->resolveErrorOutput();

        if (! $this->errorCallbacks) {
            throw $exception;
        }

        foreach ($this->errorCallbacks as $callback) {
            if (! $this->isAllowedThrowableType($exception, $callback)) {
                continue;
            }

            $callback($exception);

            break;
        }
    }

    /**
     * @return Throwable
     */
    abstract protected function resolveErrorOutput(): Throwable;

    /**
     * @return void
     */
    public function triggerTimeout(): void
    {
        foreach ($this->timeoutCallbacks as $callback) {
            call_user_func_array($callback, []);
        }
    }

    /**
     * @param Throwable $throwable
     * @param callable $callable
     * @return bool
     * @throws ReflectionException
     */
    protected function isAllowedThrowableType(Throwable $throwable, callable $callable): bool
    {
        $reflection = new ReflectionFunction($callable);

        $parameters = $reflection->getParameters();

        if (! isset($parameters[0])) {
            return true;
        }

        $firstParameter = $parameters[0];

        if (! $firstParameter) {
            return true;
        }

        $type = $firstParameter->getType();

        if (! $type) {
            return true;
        }

        if (is_a($throwable, $type->getName())) {
            return true;
        }

        return false;
    }
}
