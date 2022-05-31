<?php

namespace Spatie\Async;

abstract class Task
{
    abstract public function configure();

    abstract public function run();

    /**
     * @return mixed
     */
    public function __invoke(): mixed
    {
        $this->configure();

        return $this->run();
    }
}
