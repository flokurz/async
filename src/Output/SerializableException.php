<?php

namespace Spatie\Async\Output;

use Throwable;

class SerializableException
{
    /** @var string */
    protected string $class;

    /** @var string */
    protected string $message;

    /** @var string */
    protected string $trace;

    /**
     * @param Throwable $exception
     */
    public function __construct(Throwable $exception)
    {
        $this->class = get_class($exception);
        $this->message = $exception->getMessage();
        $this->trace = $exception->getTraceAsString();
    }

    /**
     * @return Throwable
     */
    public function asThrowable(): Throwable
    {
        try {
            /** @var Throwable $throwable */
            $throwable = new $this->class($this->message."\n\n".$this->trace);
        } catch (Throwable) {
            $throwable = new ParallelException($this->message, $this->class, $this->trace);
        }

        return $throwable;
    }
}
