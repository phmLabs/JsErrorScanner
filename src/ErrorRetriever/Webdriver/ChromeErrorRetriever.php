<?php

// php bin/JsErrorScanner.php scan -p some-project -a '416C70E7-B3B5-4CF0-8B98-16C57843E40F' -s 101 'http://yuscale.com' -k https://monitor.leankoala.com/webhook/ -o '{"browser":"chrome"}' -c 102 -u localhost -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}' -v
// php bin/JsErrorScanner.php scan -p some-project -a '416C70E7-B3B5-4CF0-8B98-16C57843E40F' -s 101 'https://www.thewebhatesme.com/wp-admin/' -k https://monitor.leankoala.com/webhook/ -o '{"browser":"phantom"}' -c 102 -u localhost -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}' -v

namespace whm\JsErrorScanner\ErrorRetriever\Webdriver;

use GuzzleHttp\Psr7\Request;
use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeResponse;
use phm\HttpWebdriverClient\Http\Client\Decorator\FileCacheDecorator;
use phm\HttpWebdriverClient\Http\Client\HeadlessChrome\HeadlessChromeClient;
use whm\Html\Uri;
use whm\JsErrorScanner\ErrorRetriever\ErrorRetriever;

class ChromeErrorRetriever implements ErrorRetriever
{
    private $host;
    private $port;
    private $nocache;

    public function __construct($host = 'http://localhost', $port = 4444, $nocache = false)
    {
        $this->port = $port;
        $this->host = $host;
        $this->nocache = $nocache;
    }

    public function getErrors(Uri $uri)
    {
        if ($this->nocache) {
            $client = new HeadlessChromeClient();
        } else {
            $chromeClient = new HeadlessChromeClient();
            $client = new FileCacheDecorator($chromeClient);
        }

        try {
            $headers = ['Accept-Encoding' => 'gzip', 'Connection' => 'keep-alive'];
            $response = $client->sendRequest(new Request('GET', $uri, $headers));
            /** @var ChromeResponse $response */
            $errors = $response->getJavaScriptErrors();
        } catch (\Exception $e) {
            $client->close();
            throw new SeleniumCrashException($e->getMessage());
        }

        $client->close();

        return $errors;
    }
}
