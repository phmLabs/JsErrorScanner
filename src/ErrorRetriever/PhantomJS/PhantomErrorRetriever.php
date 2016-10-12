<?php

namespace whm\JsErrorScanner\ErrorRetriever\PhantomJS;

use Psr\Http\Message\UriInterface;
use whm\JsErrorScanner\ErrorRetriever\ErrorRetriever;

class PhantomErrorRetriever implements ErrorRetriever
{
    private $phantomJSExec = 'phantomjs';
    private $errorJsFile = 'errors.js';
    private $errorJsTempFile;

    public function __construct($phantomJSExec = null)
    {
        if (!is_null($phantomJSExec)) {
            if (!file_exists($phantomJSExec)) {
                throw new PhantomJsRuntimeException("Unable to find phantomjs executables");
            }
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

        preg_match_all('^###error_begin###(.*?)###error_end###^s', $rawOutput, $matches);

        return $matches[1];
    }

    public function __destruct()
    {
        unlink($this->errorJsTempFile);
    }
}
