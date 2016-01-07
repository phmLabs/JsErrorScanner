<?php

namespace whm\JsErrorScanner\PhantomJS;

class PhantomJsRuntimeException extends \RuntimeException
{
    private $exitCode = 0;

    /**
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * @param int $exitCode
     */
    public function setExitCode($exitCode)
    {
        $this->exitCode = $exitCode;
    }
}