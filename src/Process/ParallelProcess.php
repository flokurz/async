<?php

namespace Spatie\Async\Process;

use Spatie\Async\Output\ParallelError;
use Spatie\Async\Output\SerializableException;
use Symfony\Component\Process\Process;
use Throwable;

class ParallelProcess implements Runnable
{
    use ProcessCallbacks;

    /** @var Process */
    protected Process $process;

    /** @var int */
    protected int $id;

    /** @var int|null */
    protected ?int $pid;

    /** @var mixed */
    protected mixed $output;

    /** @var mixed */
    protected mixed $errorOutput;

    /** @var float */
    protected float $startTime;

    /**
     * @param Process $process
     * @param int $id
     */
    public function __construct(Process $process, int $id)
    {
        $this->process = $process;
        $this->id = $id;
    }

    /**
     * @param Process $process
     * @param int $id
     * @return static
     */
    public static function create(Process $process, int $id): self
    {
        return new self($process, $id);
    }

    /**
     * @return $this
     */
    public function start(): self
    {
        $this->startTime = microtime(true);

        $this->process->start();

        $this->pid = $this->process->getPid();

        return $this;
    }

    /**
     * @param float|int $timeout
     * @return $this
     */
    public function stop(float|int $timeout = 0): self
    {
        $this->process->stop($timeout, SIGKILL);

        return $this;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->process->isSuccessful();
    }

    /**
     * @return bool
     */
    public function isTerminated(): bool
    {
        return $this->process->isTerminated();
    }

    /**
     * @return mixed
     */
    public function getOutput(): mixed
    {
        if (! $this->output) {
            $processOutput = $this->process->getOutput();

            $this->output = @unserialize(base64_decode($processOutput), ['allowed_classes' => true]);

            if (! $this->output) {
                $this->errorOutput = $processOutput;
            }
        }

        return $this->output;
    }

    /**
     * @return mixed|string
     */
    public function getErrorOutput(): mixed
    {
        if (! $this->errorOutput) {
            $processOutput = $this->process->getErrorOutput();

            $this->errorOutput = @unserialize(base64_decode($processOutput), ['allowed_classes' => true]);

            if (! $this->errorOutput) {
                $this->errorOutput = $processOutput;
            }
        }

        return $this->errorOutput;
    }

    /**
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->process;
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
        return $this->pid;
    }

    /**
     * @return float
     */
    public function getCurrentExecutionTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * @return Throwable
     */
    protected function resolveErrorOutput(): Throwable
    {
        $exception = $this->getErrorOutput();

        if ($exception instanceof SerializableException) {
            $exception = $exception->asThrowable();
        }

        if (! $exception instanceof Throwable) {
            $exception = ParallelError::fromException($exception);
        }

        return $exception;
    }
}
