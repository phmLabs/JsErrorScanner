<?php

namespace whm\JsErrorScanner\ErrorRetriever\Webdriver;

use Leankoala\RetrieverConnector\LeanRetrieverClient;
use phm\HttpWebdriverClient\Http\Client\Decorator\FileCacheDecorator;
use phm\HttpWebdriverClient\Http\Client\FallbackClient;
use phm\HttpWebdriverClient\Http\Client\HeadlessChrome\HeadlessChromeClient;
use phm\HttpWebdriverClient\Http\Client\HeadlessChrome\HeadlessChromeResponse;
use phm\HttpWebdriverClient\Http\Request\CacheAwareRequest;
use Psr\Http\Message\RequestInterface;
use whm\JsErrorScanner\ErrorRetriever\ErrorRetriever;

class ChromeErrorRetriever implements ErrorRetriever
{
    private $clientTimeout;
    private $nocache;

    public function __construct($nocache = false, $clientTimeout = 31000)
    {
        $this->clientTimeout = $clientTimeout;
        $this->nocache = $nocache;
    }

    /**
     * @param RequestInterface $request
     * @return HeadlessChromeResponse
     */
    public function getResponse(RequestInterface $request)
    {
        if ($request instanceof CacheAwareRequest) {
            $request->setIsCacheAllowed(!$this->nocache);
        }

        $leanClient = new LeanRetrieverClient('http://parent:8000');

        $fallbackClient = new FallbackClient($leanClient);

        $chromeClient = new HeadlessChromeClient($this->clientTimeout);
        $cachedClient = new FileCacheDecorator($chromeClient);

        $fallbackClient->addFallbackClient($cachedClient);

        try {
            $response = $fallbackClient->sendRequest($request);
            /** @var HeadlessChromeResponse $response */
        } catch (\Exception $e) {
            $fallbackClient->close();
            throw new $e;
        }

        $fallbackClient->close();

        return $response;
    }
}
