<?php

namespace Spatie\Async;

use ArrayAccess;
use InvalidArgumentException;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Spatie\Async\Process\ParallelProcess;
use Spatie\Async\Process\Runnable;
use Spatie\Async\Process\SynchronousProcess;
use Spatie\Async\Runtime\ParentRuntime;

class Pool implements ArrayAccess
{
    /** @var bool */
    public static bool $forceSynchronous = false;

    /** @var int */
    protected int $concurrency = 20;

    /** @var int */
    protected int $tasksPerProcess = 1;

    /** @var int */
    protected int $timeout = 300;

    /** @var int */
    protected int $sleepTime = 50000;

    /** @var Runnable[] */
    protected array $queue = [];

    /** @var Runnable[] */
    protected array $inProgress = [];

    /** @var Runnable[] */
    protected array $finished = [];

    /** @var Runnable[] */
    protected array $failed = [];

    /** @var Runnable[] */
    protected array $timeouts = [];

    /** @var array */
    protected array $results = [];

    /** @var PoolStatus */
    protected PoolStatus $status;

    /** @var bool */
    protected bool $stopped = false;

    /** @var string */
    protected string $binary = PHP_BINARY;

    public function __construct()
    {
        if (static::isSupported()) {
            $this->registerListener();
        }

        $this->status = new PoolStatus($this);
    }

    /**
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * @return bool
     */
    public static function isSupported(): bool
    {
        return
            function_exists('pcntl_async_signals')
            && function_exists('posix_kill')
            && function_exists('proc_open')
            && ! self::$forceSynchronous;
    }

    /**
     * @return $this
     */
    public function forceSynchronous(): self
    {
        self::$forceSynchronous = true;

        return $this;
    }

    /**
     * @param int $concurrency
     * @return $this
     */
    public function concurrency(int $concurrency): self
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    /**
     * @param float $timeout
     * @return $this
     */
    public function timeout(float $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $autoloader
     * @return $this
     */
    public function autoload(string $autoloader): self
    {
        ParentRuntime::init($autoloader);

        return $this;
    }

    /**
     * @param int $sleepTime
     * @return $this
     */
    public function sleepTime(int $sleepTime): self
    {
        $this->sleepTime = $sleepTime;

        return $this;
    }

    /**
     * @param string $binary
     * @return $this
     */
    public function withBinary(string $binary): self
    {
        $this->binary = $binary;

        return $this;
    }

    /**
     * @return void
     */
    public function notify(): void
    {
        if (count($this->inProgress) >= $this->concurrency) {
            return;
        }

        $process = array_shift($this->queue);

        if (! $process) {
            return;
        }

        $this->putInProgress($process);
    }

    /**
     * @param callable|Runnable $process
     * @param int|null $outputLength
     * @return Runnable
     * @throws PhpVersionNotSupportedException
     */
    public function add(callable|Runnable $process, ?int $outputLength = null): Runnable
    {
        if (! is_callable($process) && ! $process instanceof Runnable) {
            throw new InvalidArgumentException('The process passed to Pool::add should be callable.');
        }

        if (! $process instanceof Runnable) {
            $process = ParentRuntime::createProcess(
                $process,
                $outputLength,
                $this->binary
            );
        }

        $this->putInQueue($process);

        return $process;
    }

    /**
     * @param callable|null $intermediateCallback
     * @return array
     */
    public function wait(?callable $intermediateCallback = null): array
    {
        while ($this->inProgress) {
            foreach ($this->inProgress as $process) {
                if ($process->getCurrentExecutionTime() > $this->timeout) {
                    $this->markAsTimedOut($process);
                }

                if ($process instanceof SynchronousProcess) {
                    $this->markAsFinished($process);
                }
            }

            if (! $this->inProgress) {
                break;
            }

            if ($intermediateCallback) {
                $intermediateCallback($this);
            }

            usleep($this->sleepTime);
        }

        return $this->results;
    }

    /**
     * @param Runnable $process
     * @return void
     */
    public function putInQueue(Runnable $process): void
    {
        $this->queue[$process->getId()] = $process;

        $this->notify();
    }

    /**
     * @param Runnable $process
     * @return void
     */
    public function putInProgress(Runnable $process): void
    {
        if ($this->stopped) {
            return;
        }

        if ($process instanceof ParallelProcess) {
            $process->getProcess()->setTimeout($this->timeout);
        }

        $process->start();

        unset($this->queue[$process->getId()]);

        $this->inProgress[$process->getPid()] = $process;
    }

    /**
     * @param Runnable $process
     * @return void
     */
    public function markAsFinished(Runnable $process): void
    {
        unset($this->inProgress[$process->getPid()]);

        $this->notify();

        $this->results[] = $process->triggerSuccess();

        $this->finished[$process->getPid()] = $process;
    }

    /**
     * @param Runnable $process
     * @return void
     */
    public function markAsTimedOut(Runnable $process): void
    {
        unset($this->inProgress[$process->getPid()]);

        $process->stop();

        $process->triggerTimeout();
        $this->timeouts[$process->getPid()] = $process;

        $this->notify();
    }

    /**
     * @param Runnable $process
     * @return void
     */
    public function markAsFailed(Runnable $process): void
    {
        unset($this->inProgress[$process->getPid()]);

        $this->notify();

        $process->triggerError();

        $this->failed[$process->getPid()] = $process;
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        // TODO

        return false;
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetGet($offset): bool
    {
        // TODO

        return false;
    }

    /**
     * @param $offset
     * @param $value
     * @return void
     * @throws PhpVersionNotSupportedException
     */
    public function offsetSet($offset, $value): void
    {
        $this->add($value);
    }

    /**
     * @param $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        // TODO
    }

    /**
     * @return Runnable[]
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    /**
     * @return Runnable[]
     */
    public function getInProgress(): array
    {
        return $this->inProgress;
    }

    /**
     * @return Runnable[]
     */
    public function getFinished(): array
    {
        return $this->finished;
    }

    /**
     * @return Runnable[]
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * @return Runnable[]
     */
    public function getTimeouts(): array
    {
        return $this->timeouts;
    }

    public function status(): PoolStatus
    {
        return $this->status;
    }

    /**
     * @return void
     */
    protected function registerListener(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGCHLD, function ($signo, $status) {
            while (true) {
                $pid = pcntl_waitpid(-1, $processState, WNOHANG | WUNTRACED);

                if ($pid <= 0) {
                    break;
                }

                $process = $this->inProgress[$pid] ?? null;

                if (! $process) {
                    continue;
                }

                if ($status['status'] === 0) {
                    $this->markAsFinished($process);

                    continue;
                }

                $this->markAsFailed($process);
            }
        });
    }

    /**
     * @return void
     */
    public function stop(): void
    {
        $this->stopped = true;
    }
}
