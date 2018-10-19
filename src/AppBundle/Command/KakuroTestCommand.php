<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use AppBundle\Process\KakuroUniqueFinder;

class KakuroTestCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('kakuro:test')
            ->setDescription('...')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parameters = array_merge($input->getArguments(), $input->getOptions());
        $parameters['output'] = $output;
        // find uq 3x2s
        KakuroUniqueFinder::autoExecute($parameters, null);

    }

}
