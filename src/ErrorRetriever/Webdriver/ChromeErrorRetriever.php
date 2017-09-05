<?php

// php bin/JsErrorScanner.php scan -p some-project -a '416C70E7-B3B5-4CF0-8B98-16C57843E40F' -s 101 'http://yuscale.com' -k https://monitor.leankoala.com/webhook/ -o '{"browser":"chrome"}' -c 102 -u localhost -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}' -v
// php bin/JsErrorScanner.php scan -p some-project -a '416C70E7-B3B5-4CF0-8B98-16C57843E40F' -s 101 'https://www.thewebhatesme.com/wp-admin/' -k https://monitor.leankoala.com/webhook/ -o '{"browser":"phantom"}' -c 102 -u localhost -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}' -v

namespace whm\JsErrorScanner\ErrorRetriever\Webdriver;

use GuzzleHttp\Psr7\Request;
use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeClient;
use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeResponse;
use phm\HttpWebdriverClient\Http\Client\Decorator\FileCacheDecorator;
use whm\Html\Uri;
use whm\JsErrorScanner\ErrorRetriever\ErrorRetriever;

class ChromeErrorRetriever implements ErrorRetriever
{
    private $host;
    private $port;

    public function __construct($host = 'http://localhost', $port = 4444)
    {
        $this->port = $port;
        $this->host = $host;
    }

    public function getErrors(Uri $uri)
    {
        $chromeClient = new ChromeClient($this->host, $this->port);
        $cachedClient = new FileCacheDecorator($chromeClient);

        if ($uri->getCookieString()) {
            $preparedUri = (string)$uri . '#cookie=' . $uri->getCookieString();
        } else {
            $preparedUri = (string)$uri;
        }

        try {
            $headers = ['Accept-Encoding' => 'gzip', 'Connection' => 'keep-alive'];
            $response = $cachedClient->sendRequest(new Request('GET', $preparedUri, $headers));
            /** @var ChromeResponse $response */
            $errors = $response->getJavaScriptErrors();
        } catch (\Exception $e) {
            if (isset($driver)) {
                $driver->quit();
            }
            throw new SeleniumCrashException($e->getMessage());
        }

        $errorList = explode('###', $errors);

        $filteredErrors = [];

        foreach ($errorList as $errorElement) {
            if ($errorElement != "") {
                $filteredErrors[] = trim($errorElement);
            }
        }

        if (isset($driver)) {
            $driver->quit();
        }

        return $filteredErrors;
    }
}
