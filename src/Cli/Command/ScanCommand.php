<?php

namespace whm\JsErrorScanner\Cli\Command;

use GuzzleHttp\Client;

use Koalamon\Client\Reporter\Event;
use Koalamon\Client\Reporter\KoalamonException;
use Koalamon\Client\Reporter\Reporter;
use Koalamon\CookieMakerHelper\CookieMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use whm\Html\Uri;
use whm\JsErrorScanner\ErrorRetriever\ErrorRetriever;
use whm\JsErrorScanner\ErrorRetriever\PhantomJS\PhantomErrorRetriever;
use whm\JsErrorScanner\ErrorRetriever\Webdriver\ChromeErrorRetriever;
use whm\JsErrorScanner\ErrorRetriever\Webdriver\SeleniumCrashException;


class ScanCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be checked'),
                new InputOption('koalamon_project', 'p', InputOption::VALUE_OPTIONAL, 'the koalamon project', null),
                new InputOption('koalamon_project_api_key', 'a', InputOption::VALUE_OPTIONAL, 'the koalamon api key', null),
                new InputOption('koalamon_system', 's', InputOption::VALUE_OPTIONAL, 'the koalamon system identifier', null),
                new InputOption('koalamon_server', 'k', InputOption::VALUE_OPTIONAL, 'the koalamon server', null),
                new InputOption('phantomjs_exec', 'j', InputOption::VALUE_OPTIONAL, 'the phantom js executable file', null),
                new InputOption('selenium_server', 'u', InputOption::VALUE_OPTIONAL, 'the selenium server url, ', 'http://localhost'),
                new InputOption('selenium_server_port', 'i', InputOption::VALUE_OPTIONAL, 'the selenium server port, ', 4444),
                new InputOption('options', 'o', InputOption::VALUE_OPTIONAL, 'koalamon options', null),
                new InputOption('component', 'c', InputOption::VALUE_OPTIONAL, 'koalamon component id', null),
                new InputOption('login', 'l', InputOption::VALUE_OPTIONAL, 'login params', null),
                new InputOption('errorLog', 'e', InputOption::VALUE_OPTIONAL, 'login params', '/tmp/log/jserrorscanner.log'),
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

        if (!array_key_exists('browser', $options) || $options['browser'] === 'chrome') {
            $errorRetriever = new ChromeErrorRetriever($input->getOption('selenium_server'), $input->getOption('selenium_server_port'));
        } else {
            $errorRetriever = new PhantomErrorRetriever($input->getOption('phantomjs_exec'));
        }

        /** @var ErrorRetriever $errorRetriever */

        $uri = new Uri($input->getArgument('url'));

        if ($input->getOption('login')) {
            $cookies = new CookieMaker();
            $cookies = $cookies->getCookies($input->getOption('login'));
            $uri->addCookies($cookies);
        }

        try {
            $errors = $errorRetriever->getErrors($uri);
        } catch (SeleniumCrashException $e) {
            $output->writeln("<error>" . $e->getMessage() . "\n</error>");
            exit(1);
        }

        $ignoredFiles = [];
        if ($input->getOption('options')) {
            $optionArray = json_decode($input->getOption('options'), true);

            if (is_array($optionArray)) {
                if (array_key_exists('excludedFiles', $optionArray)) {
                    foreach ($optionArray['excludedFiles'] as $excludedFile) {
                        $ignoredFiles[] = $excludedFile['filename'];
                    }
                }
            }
        }

        $errorFound = false;
        $status = Event::STATUS_FAILURE;
        $errorMsg = "";

        if (count($errors) > 0) {
            $errorMsg = 'JavaScript errors (' . count($errors) . ') were found on ' . $input->getArgument('url') . '<ul>';

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
            $output->writeln('   No errors found.');
            $errorMsg = 'No javascript errors found for ' . $input->getArgument('url');
            $status = Event::STATUS_SUCCESS;
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

            $event = new Event('JsErrorScanner_' . $input->getOption('component'), $system, $status, 'JsErrorScanner', $errorMsg, count($errors), null, $input->getOption('component'));

            try {
                $reporter->sendEvent($event);
            } catch (KoalamonException $e) {
                $output->writeln('');
                $output->writeln('<error> ' . $e->getMessage() . ' </error>');
                $output->writeln(' Url: ' . $e->getUrl());
                $output->writeln(' Payload: ' . $e->getPayload());
            }
        }

        $output->writeln('');
    }
}
