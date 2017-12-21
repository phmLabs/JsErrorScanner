<?php

namespace whm\JsErrorScanner\ErrorRetriever;

use Psr\Http\Message\RequestInterface;
use whm\Html\Uri;

interface ErrorRetriever
{
    public function getResponse(RequestInterface $request);
}
