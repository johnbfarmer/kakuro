<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use AppBundle\Process\BuildTables;

class BuildTablesCommand extends Command
{
    protected 
        $em,
        $parameters;

    protected function configure()
    {
        $this->setName('kakuro:build-tables')
            ->setDescription('x')
            ->setHelp('y')
            ->addOption(
                'target', null, InputOption::VALUE_REQUIRED, 'sum of set'
            )
            ->addOption(
                'size', null, InputOption::VALUE_REQUIRED, 'size of set'
            )
            ->addOption(
                'number_set', null, InputOption::VALUE_REQUIRED, '1,2,3,...'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('KAKURO TABLES BUILD');
        $this->parameters = array_merge($input->getArguments(), $input->getOptions());
        $parameters = $this->parameters;
        $size = $parameters['size'];
        $target = $parameters['target'];
        $t = BuildTables::autoExecute($parameters, null);
        $output->writeln(json_encode($t->getResult()));
    }
}

