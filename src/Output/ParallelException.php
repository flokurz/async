<?php

namespace Spatie\Async\Output;

use Exception;

class ParallelException extends Exception
{
    /** @var string */
    protected string $originalClass;

    /** @var string */
    protected string $originalTrace;

    /**
     * @param string $message
     * @param string $originalClass
     * @param string $originalTrace
     */
    public function __construct(string $message, string $originalClass, string $originalTrace)
    {
        parent::__construct($message);
        $this->originalClass = $originalClass;
        $this->originalTrace = $originalTrace;
    }

    /**
     * @return string
     */
    public function getOriginalClass(): string
    {
        return $this->originalClass;
    }

    /**
     * @return string
     */
    public function getOriginalTrace(): string
    {
        return $this->originalTrace;
    }
}
