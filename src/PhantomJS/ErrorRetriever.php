<?php

namespace whm\JsErrorScanner\PhantomJS;

use Psr\Http\Message\UriInterface;

class ErrorRetriever
{
    private $phantomJSExec = 'phantomjs';
    private $errorJsFile = 'errors.js';
    private $errorJsTempFile;

    public function __construct($phantomJSExec = null)
    {
        if (!is_null($phantomJSExec)) {
            $this->phantomJSExec = $phantomJSExec;
        }

        $this->errorJsTempFile = \tempnam('jserror', 'jserror_');

        copy(__DIR__ . '/' . $this->errorJsFile, $this->errorJsTempFile);
    }

    public function getErrors(UriInterface $uri)
    {
        $command = $this->phantomJSExec . ' ' . $this->errorJsTempFile . ' ' . (string)$uri;

        exec($command, $output, $exitCode);

        $rawOutput = implode($output, "\n");

        if ($exitCode > 0) {
            $e = new PhantomJsRuntimeException('Phantom exits with exit code ' . $exitCode . PHP_EOL . $rawOutput);
            $e->setExitCode($exitCode);
            throw $e;
        }

        return $output;
    }

    public function __destruct()
    {
        unlink($this->errorJsTempFile);
    }
}
