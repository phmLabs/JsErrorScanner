<?php

namespace whm\JsErrorScanner\ErrorRetriever;

use whm\Html\Uri;

interface ErrorRetriever
{
    public function getResponse(Uri $uri);
}
