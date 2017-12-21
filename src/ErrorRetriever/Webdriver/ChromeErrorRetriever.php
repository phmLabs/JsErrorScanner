<?php

namespace whm\JsErrorScanner\ErrorRetriever\Webdriver;

use phm\HttpWebdriverClient\Http\Client\Decorator\FileCacheDecorator;
use phm\HttpWebdriverClient\Http\Client\HeadlessChrome\HeadlessChromeClient;
use phm\HttpWebdriverClient\Http\Client\HeadlessChrome\HeadlessChromeResponse;
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
        if ($this->nocache) {
            $client = new HeadlessChromeClient($this->clientTimeout);
        } else {
            $chromeClient = new HeadlessChromeClient($this->clientTimeout);
            $client = new FileCacheDecorator($chromeClient);
        }

        try {
            $response = $client->sendRequest($request);
            /** @var HeadlessChromeResponse $response */
        } catch (\Exception $e) {
            $client->close();
            throw new $e;
        }

        $client->close();

        return $response;
    }
}
