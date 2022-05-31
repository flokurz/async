<?php

use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Spatie\Async\Pool;
use Spatie\Async\Process\ParallelProcess;
use Spatie\Async\Process\Runnable;
use Spatie\Async\Runtime\ParentRuntime;
use Spatie\Async\Task;

if (! function_exists('async')) {
    /**
     * @param callable|Task $task
     * @return ParallelProcess
     * @throws PhpVersionNotSupportedException
     */
    function async(callable|Task $task): Runnable
    {
        return ParentRuntime::createProcess($task);
    }
}

if (! function_exists('await')) {
    /**
     * @param Pool $pool
     * @return array
     */
    function await(Pool $pool): array
    {
        return $pool->wait();
    }
}
