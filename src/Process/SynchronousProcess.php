<?php

namespace Spatie\Async\Process;

use Spatie\Async\Task;
use Throwable;

class SynchronousProcess implements Runnable
{
    use ProcessCallbacks;

    /** @var int */
    protected int $id;

    /** @var callable */
    protected $task;

    /** @var mixed */
    protected mixed $output;

    /** @var mixed */
    protected mixed$errorOutput;

    /** @var float */
    protected float $executionTime;

    /**
     * @param callable $task
     * @param int $id
     */
    public function __construct(callable $task, int $id)
    {
        $this->id = $id;
        $this->task = $task;
    }

    /**
     * @param callable $task
     * @param int $id
     * @return static
     */
    public static function create(callable $task, int $id): self
    {
        return new self($task, $id);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getPid(): ?int
    {
        return $this->getId();
    }

    /**
     * @return void
     */
    public function start(): void
    {
        $startTime = microtime(true);

        if ($this->task instanceof Task) {
            $this->task->configure();
        }

        try {
            $this->output = $this->task instanceof Task
                ? $this->task->run()
                : call_user_func($this->task);
        } catch (Throwable $throwable) {
            $this->errorOutput = $throwable;
        } finally {
            $this->executionTime = microtime(true) - $startTime;
        }
    }

    /**
     * @param float|int $timeout
     * @return bool
     */
    public function stop(float|int $timeout = 0): bool
    {
        return true;
    }

    /**
     * @return mixed
     */
    public function getOutput(): mixed
    {
        return $this->output;
    }

    /**
     * @return mixed
     */
    public function getErrorOutput(): mixed
    {
        return $this->errorOutput;
    }

    /**
     * @return float
     */
    public function getCurrentExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * @return Throwable
     */
    protected function resolveErrorOutput(): Throwable
    {
        return $this->getErrorOutput();
    }
}
