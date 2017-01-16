<?php

// php bin/JsErrorScanner.php scan -p some-project -a '416C70E7-B3B5-4CF0-8B98-16C57843E40F' -s 101 'http://yuscale.com' -k https://monitor.leankoala.com/webhook/ -o '{"browser":"chrome"}' -c 102 -u localhost -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}' -v
// php bin/JsErrorScanner.php scan -p some-project -a '416C70E7-B3B5-4CF0-8B98-16C57843E40F' -s 101 'https://www.thewebhatesme.com/wp-admin/' -k https://monitor.leankoala.com/webhook/ -o '{"browser":"phantom"}' -c 102 -u localhost -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}' -v

namespace whm\JsErrorScanner\ErrorRetriever\Webdriver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
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
        $host = $this->host . ':' . $this->port . '/wd/hub';

        $options = new ChromeOptions();

        $options->addExtensions(array(
            __DIR__ . '/extension/console2var.crx',
            __DIR__ . '/cookie_crx/cookie_extension.crx'
        ));

        $caps = DesiredCapabilities::chrome();
        $caps->setCapability(ChromeOptions::CAPABILITY, $options);

        $filteredErrors = [];

        if ($uri->getCookieString()) {
            $preparedUri = (string)$uri . '#cookie=' . $uri->getCookieString();
        } else {
            $preparedUri = (string)$uri;
        }

        try {
            $driver = RemoteWebDriver::create($host, $caps);
            $driver->get($preparedUri);

            $errors = $driver->executeScript("return localStorage.getItem(\"js_errors\")", array());
        } catch (\Exception $e) {
            throw new SeleniumCrashException($e->getMessage());
        }

        $errorList = explode('###', $errors);

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
