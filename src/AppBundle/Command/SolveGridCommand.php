<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use AppBundle\Process\SolveGrid;

class SolveGridCommand extends Command
{
    protected 
        $em,
        $parameters;

    protected function configure()
    {
        $this->setName('kakuro:solve-grid')
            ->setDescription('x')
            ->setHelp('y')
            ->addArgument(
                'hsums', InputArgument::OPTIONAL, ''
            )
            ->addArgument(
                'vsums', InputArgument::OPTIONAL, ''
            )
            ->addOption(
                'file', 'f', InputOption::VALUE_REQUIRED, ''
            )
            ->addOption(
                'clear-log', 'c', InputOption::VALUE_NONE, ''
            )
            ->addOption(
                'solutions', 's', InputOption::VALUE_REQUIRED, ''
            )
            ->addOption(
                'browser', 'b', InputOption::VALUE_NONE, 'open in browser'
            )
            ->addOption(
                'time-limit', 't', InputOption::VALUE_REQUIRED, ''
            )
            ->addOption(
                'batch-size', 'B', InputOption::VALUE_REQUIRED, 'how many grids to process per iteration'
            )
            ->addOption(
                'buffer-size', 'F', InputOption::VALUE_REQUIRED, 'how many grids to keep in storage'
            )
            ->addOption(
                'debug', '', InputOption::VALUE_NONE, ''
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('KAKURO TABLES SOLVER');
        $this->parameters = array_merge($input->getArguments(), $input->getOptions());
        $parameters = $this->parameters;
        $hsums = !empty($parameters['hsums']) ? json_decode($parameters['hsums']) : [];
        $vsums = !empty($parameters['vsums']) ? json_decode($parameters['vsums']) : [];
        $parameters['sums'] = ['h' => $hsums, 'v' => $vsums];
        $t = SolveGrid::autoExecute($parameters, null);
    }
}

