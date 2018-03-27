<?php

namespace whm\JsErrorScanner\Cli\Command;

use GuzzleHttp\Client;

use Koalamon\Client\Reporter\Event;
use Koalamon\Client\Reporter\KoalamonException;
use Koalamon\Client\Reporter\Reporter;
use Koalamon\CookieMakerHelper\CookieMaker;
use Leankoala\Devices\DeviceFactory;
use phm\HttpWebdriverClient\Http\Request\BrowserRequest;
use phm\HttpWebdriverClient\Http\Response\TimeoutAwareResponse;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use whm\Html\Uri;
use whm\JsErrorScanner\ErrorRetriever\ErrorRetriever;
use whm\JsErrorScanner\ErrorRetriever\Webdriver\ChromeErrorRetriever;


class ScanCommand extends Command
{
    private $defaultHeaders = ['Accept-Encoding' => 'gzip', 'Connection' => 'keep-alive'];

    const TYPE_INTERNAL = 'internal';
    const TYPE_EXTERNAL = 'external';

    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be checked'),
                new InputOption('koalamon_project', 'p', InputOption::VALUE_OPTIONAL, 'the koalamon project', null),
                new InputOption('koalamon_project_api_key', 'a', InputOption::VALUE_OPTIONAL, 'the koalamon api key', null),
                new InputOption('koalamon_system', 's', InputOption::VALUE_OPTIONAL, 'the koalamon system identifier', null),
                new InputOption('koalamon_server', 'k', InputOption::VALUE_OPTIONAL, 'the koalamon server', null),
                new InputOption('options', 'o', InputOption::VALUE_OPTIONAL, 'koalamon options', null),
                new InputOption('client_timeout', 't', InputOption::VALUE_OPTIONAL, 'headless crhome timeout', 31000),
                new InputOption('component', 'c', InputOption::VALUE_OPTIONAL, 'koalamon component id', null),
                new InputOption('login', 'l', InputOption::VALUE_OPTIONAL, 'login params', null),
                new InputOption('errorLog', 'e', InputOption::VALUE_OPTIONAL, 'login params', '/tmp/log/jserrorscanner.log'),
                new InputOption('nocache', null, InputOption::VALUE_NONE, 'disable cache'),
                new InputOption('device', 'd', InputOption::VALUE_OPTIONAL, 'Device', 'MacBookPro152017'),
            ))
            ->setDescription('Check an url for js errors.')
            ->setName('scan');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("\n  <info>Checking " . $input->getArgument('url') . "</info>\n");

        $options = json_decode($input->getOption('options'), true);

        if (!$options) {
            $options = array();
        }

        $errorRetriever = new ChromeErrorRetriever($input->getOption('nocache'), $input->getOption('client_timeout'));

        /** @var ErrorRetriever $errorRetriever */
        $uri = new Uri($input->getArgument('url'));

        $request = new BrowserRequest('GET', $uri, $this->defaultHeaders);

        $factory = new DeviceFactory();
        $request->setDevice($factory->create($input->getOption('device')));

        if ($input->getOption('login')) {
            $cookies = new CookieMaker();
            $cookies = $cookies->getCookies($input->getOption('login'));
            $request = $request->withCookies($cookies);
        }

        try {
            $response = $errorRetriever->getResponse($request);
            $errors = $response->getJavaScriptErrors();
        } catch (\Exception $e) {
            $output->writeln(" <error> " . $e->getMessage() . " \n</error>");
            exit(1);
        }

        // There is a bug in headless chrome that produces a ServiceWorkerRegistration error
        $ignoredFiles = ['Failed to get a ServiceWorkerRegistration'];

        if (is_array($options)) {
            if (array_key_exists('excludedFiles', $options)) {
                foreach ($options['excludedFiles'] as $excludedFile) {
                    $ignoredFiles[] = $excludedFile['filename'];
                }
            }
        }

        $splittedErrors = $this->splitErrors($errors, $uri);

        if (array_key_exists('thirdparty', $options) && $options['thirdparty'] == 'on') {
            $external = false;
        } else {
            $external = true;
        }

        $this->processErrors($splittedErrors[self::TYPE_INTERNAL], self::TYPE_INTERNAL, $ignoredFiles, $response, $input, $output);

        if ($external) {
            $this->processErrors($splittedErrors[self::TYPE_EXTERNAL], self::TYPE_EXTERNAL, $ignoredFiles, $response, $input, $output);
        }

        $output->writeln('');
    }

    private function splitErrors($errors, Uri $uri)
    {
        $splittedErrors = [self::TYPE_INTERNAL => [], self::TYPE_EXTERNAL => []];

        foreach ($errors as $error) {
            $pattern = "/at (.*?):[0-9]*:[0-9]*/";

            preg_match($pattern, $error, $matches);

            if (count($matches) > 0) {
                $file = $matches[1];

                $errorUri = new Uri($file);

                if ($errorUri->getHost() == $uri->getHost()) {
                    $type = self::TYPE_INTERNAL;
                } else {
                    $type = self::TYPE_EXTERNAL;
                }
            } else {
                $type = self::TYPE_INTERNAL;
            }

            $splittedErrors[$type][] = $error;

        }

        return $splittedErrors;
    }

    protected function processErrors($errors, $type, $ignoredFiles, ResponseInterface $response, InputInterface $input, OutputInterface $output)
    {
        $errorFound = false;

        $errorMsg = '';

        if ($type != self::TYPE_INTERNAL) {
            $identifier = 'JsErrorScanner_' . $type . '_' . $input->getOption('component');
            $tool = 'JsErrorScanner_' . $type;
        } else {
            $identifier = 'JsErrorScanner_' . $input->getOption('component');
            $tool = 'JsErrorScanner';
        }

        if (count($errors) > 0) {
            $errorMsg = 'JavaScript errors (' . count($errors) . ', ' . $type . ') were found on ' . $input->getArgument('url') . '<ul>';

            foreach ($errors as $error) {

                $ignored = false;

                foreach ($ignoredFiles as $ignoredFile) {
                    if (preg_match('^' . $ignoredFile . '^', $error)) {
                        $ignored = true;
                    }
                }
                if (!$ignored) {
                    $output->writeln('  - ' . $error);
                    $errorMsg .= '<li>' . $error . '</li>';
                    $errorFound = true;
                }
            }
            $errorMsg .= '</ul>';
        }

        if (!$errorFound) {
            $output->writeln('   No errors found (' . $type . ').');
            $errorMsg = 'No javascript errors found for ' . $input->getArgument('url');
            $status = Event::STATUS_SUCCESS;
        } else {
            $status = Event::STATUS_FAILURE;
        }

        if ($input->getOption('koalamon_project_api_key')) {
            if ($input->getOption('koalamon_server')) {
                $reporter = new Reporter($input->getOption('koalamon_project'), $input->getOption('koalamon_project_api_key'), new Client(), $input->getOption('koalamon_server'));
            } else {
                $reporter = new Reporter($input->getOption('koalamon_project'), $input->getOption('koalamon_project_api_key'), new Client());
            }

            if ($input->getOption('koalamon_system')) {
                $system = $input->getOption('koalamon_system');
            } else {
                $system = str_replace('http://', '', $input->getArgument('url'));
            }

            $event = new Event($identifier, $system, $status, $tool, $errorMsg, count($errors), null, $input->getOption('component'));

            if ($response instanceof TimeoutAwareResponse) {
                $event->addAttribute(new Event\Attribute('timeout', $response->isTimeout()));
            }

            try {
                $reporter->sendEvent($event);
            } catch (KoalamonException $e) {
                $output->writeln('');
                $output->writeln(' <error> ' . $e->getMessage() . ' </error > ');
                $output->writeln(' Url: ' . $e->getUrl());
                $output->writeln(' Payload: ' . $e->getPayload());
            }
        }
    }
}
