<?php

namespace whm\JsErrorScanner\ErrorRetriever\Webdriver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Psr\Http\Message\UriInterface;
use whm\JsErrorScanner\ErrorRetriever\ErrorRetriever;

class ChromeErrorRetriever implements ErrorRetriever
{
    private $host;
    private $port;

    public function __construct($host, $port)
    {
        $this->port = $port;
        $this->host = $host;
    }

    public function getErrors(UriInterface $uri)
    {
        $host = $this->host . ':' . $this->port . '/wd/hub';

        $options = new ChromeOptions();

        $options->addExtensions(array(
            __DIR__ . '/extension/console2var.crx'
        ));

        $caps = DesiredCapabilities::chrome();
        $caps->setCapability(ChromeOptions::CAPABILITY, $options);

        $driver = RemoteWebDriver::create($host, $caps);

        $filteredErrors = [];

        try {
            $driver->get((string)$uri);
            $errors = $driver->executeScript("return localStorage.getItem(\"js_errors\")", array());

            $errorList = explode('###', $errors);

            foreach ($errorList as $errorElement) {
                if ($errorElement != "") {
                    $filteredErrors[] = trim($errorElement);
                }
            }

        } catch
        (Exception $e) {

        }

        return $filteredErrors;
    }
}