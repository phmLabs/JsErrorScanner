<?php

namespace whm\JsErrorScanner\Cli\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Koalamon\Client\Reporter\Event;
use Koalamon\Client\Reporter\Reporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use whm\JsErrorScanner\PhantomJS\ErrorRetriever;
use whm\JsErrorScanner\PhantomJS\HarRetriever;
use whm\JsErrorScanner\Reporter\Incident;
use whm\JsErrorScanner\Reporter\XUnit;

class ScanCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be checked'),
                new InputOption('koalamon_project', 'p', InputOption::VALUE_OPTIONAL, 'the koalamon project', null),
                new InputOption('koalamon_project_api_key', 'k', InputOption::VALUE_OPTIONAL, 'the koalamon api key', null),
                new InputOption('ignore_list', 'i', InputOption::VALUE_OPTIONAL, 'the irgnoe list file', null),
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

        $errorRetriever = new ErrorRetriever();
        $errors = $errorRetriever->getErrors(new Uri($input->getArgument('url')));

        $ignores = array();
        $errorFound = false;

        if ($input->getOption('ignore_list')) {
            $ignoreFile = Yaml::parse(file_get_contents($input->getOption('ignore_list')));
            $ignores = $ignoreFile['ignore'];
        }

        if (count($errors) > 0) {
            $errorMsg = 'JavaScript errors (' . count($errors) . ') where found  on ' . $input->getArgument('url') . '<ul>';
            foreach ($errors as $error) {
                if (!in_array($error, $ignores)) {
                    $output->writeln('  - ' . $error);
                    $errorMsg .= '<li>' . $error . '</li>';
                    $errorFound = true;
                }
            }
            $errorMsg .= '</ul>';
            $status = Event::STATUS_FAILURE;
        }

        if (!$errorFound) {
            $output->writeln('   No errors found.');
            $errorMsg = '';
            $status = Event::STATUS_SUCCESS;
        }

        if ($input->getOption('koalamon_project_api_key')) {
            $reporter = new Reporter($input->getOption('koalamon_project'), $input->getOption('koalamon_project_api_key'), new Client());
            $system = str_replace('http://', '', $input->getArgument('url'));
            $event = new Event('JsErrorScanner_' . $system, $system, $status, 'JsErrorScanner', $errorMsg, count($errors));
            $reporter->sendEvent($event, true);
        }

        $output->writeln('');
    }
}
