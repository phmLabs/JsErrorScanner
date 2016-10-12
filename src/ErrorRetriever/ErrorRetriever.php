<?php

namespace whm\JsErrorScanner\ErrorRetriever;

use Psr\Http\Message\UriInterface;

interface ErrorRetriever
{
    public function getErrors(UriInterface $uri);
}
