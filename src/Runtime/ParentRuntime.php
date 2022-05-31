<?php

namespace Spatie\Async\Runtime;

use Closure;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;
use Spatie\Async\Pool;
use Spatie\Async\Process\ParallelProcess;
use Spatie\Async\Process\Runnable;
use Spatie\Async\Process\SynchronousProcess;
use Spatie\Async\Task;
use Symfony\Component\Process\Process;

class ParentRuntime
{
    /** @var bool */
    protected static bool $isInitialised = false;

    /** @var string|bool */
    protected static string|bool $autoloader;

    /** @var string */
    protected static string $childProcessScript;

    /** @var int */
    protected static int $currentId = 0;

    /** @var null|false|int */
    protected static int|null|false $myPid = null;

    /**
     * @param string|null $autoloader
     * @return void
     */
    public static function init(string $autoloader = null): void
    {
        if (null === $autoloader) {
            $existingAutoloaderFiles = array_filter([
                __DIR__.'/../../../../autoload.php',
                __DIR__.'/../../../autoload.php',
                __DIR__.'/../../vendor/autoload.php',
                __DIR__.'/../../../vendor/autoload.php',
            ], static function (string $path) {
                return file_exists($path);
            });

            $tempAutoloader = reset($existingAutoloaderFiles);
        } else {
            $tempAutoloader = $autoloader;
        }

        self::$autoloader = $tempAutoloader;
        self::$childProcessScript = __DIR__.'/ChildRuntime.php';

        self::$isInitialised = true;
    }

    /**
     * @param callable|Task $task
     * @param int|null $outputLength
     * @param string|null $binary
     * @return Runnable
     * @throws PhpVersionNotSupportedException
     */
    public static function createProcess(callable|Task $task, ?int $outputLength = null, ?string $binary = 'php'): Runnable
    {
        if (! self::$isInitialised) {
            self::init();
        }

        if (! Pool::isSupported()) {
            return SynchronousProcess::create($task, self::getId());
        }

        $process = new Process([
            $binary,
            self::$childProcessScript,
            self::$autoloader,
            self::encodeTask($task),
            $outputLength,
        ]);

        return ParallelProcess::create($process, self::getId());
    }

    /**
     * @param callable|Task $task
     * @return string
     * @throws PhpVersionNotSupportedException
     */
    public static function encodeTask(callable|Task $task): string
    {
        if ($task instanceof Closure) {
            $serializableTask = new SerializableClosure($task);
        } else {
            $serializableTask = $task;
        }

        return base64_encode(serialize($serializableTask));
    }

    /**
     * @param string $task
     * @return mixed
     */
    public static function decodeTask(string $task): mixed
    {
        return unserialize(base64_decode($task), ['allowed_classes' => true]);
    }

    /**
     * @return string
     */
    protected static function getId(): string
    {
        if (self::$myPid === null) {
            self::$myPid = getmypid();
        }

        ++self::$currentId;

        return self::$currentId . self::$myPid;
    }
}
