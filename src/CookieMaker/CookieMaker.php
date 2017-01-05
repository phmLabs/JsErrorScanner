<?php

namespace whm\JsErrorScanner\CookieMaker;

class CookieMaker
{
    private $executable;

    public function __construct($executable = './CookieMaker')
    {
        $this->executable = $executable;
    }

    public function getCookies($session)
    {
        if (!is_string($session)) {
            $session = json_encode($session);
        }

        $command = $this->executable . " '" . $session . "'";

        exec($command, $output, $result);

        $cookies = json_decode($output[0], true);

        return $cookies;
    }

    public function getCookieString($session)
    {
        $cookies = $this->getCookies();

        $cookieString = "";

        foreach ($cookies as $key => $value) {
            $cookieString .= $key . '=' . $value . '; ';
        }

        return $cookieString;
    }
}