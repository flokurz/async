<?php

namespace Spatie\Async\Process;

interface Runnable
{
    public function getId(): int;

    public function getPid(): ?int;

    public function start();

    /**
     * @param callable $callback
     * @return Runnable
     */
    public function then(callable $callback): self;

    /**
     * @param callable $callback
     * @return Runnable
     */
    public function catch(callable $callback): self;

    /**
     * @param callable $callback
     * @return Runnable
     */
    public function timeout(callable $callback): self;

    /**
     * @param float|int $timeout The timeout in seconds
     * @return mixed
     */
    public function stop(float|int $timeout = 0): mixed;

    public function getOutput();

    public function getErrorOutput();

    public function triggerSuccess();

    public function triggerError();

    public function triggerTimeout();

    public function getCurrentExecutionTime(): float;
}
