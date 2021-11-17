<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use AppBundle\Process\BuildTables;

class BuildFullTableCommand extends Command
{
    protected 
        $em,
        $parameters;

    protected function configure()
    {
        $this->setName('kakuro:build-full-table')
            ->setDescription('x')
            ->setHelp('y');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parameters = [];
        $res = [];
        $s = 2;
        for ($t=3; $t<=45 ; $t++) {
            $res[$t] = [];
            for ($s=2; $s<=9 ; $s++) {
                $parameters['size'] = $s;
                $parameters['target'] = $t;
                $bt = BuildTables::autoExecute($parameters, null);
                $res[$t][] = [$s => $bt->getResult()];
            }
        }
        $output->writeln(json_encode($res));
    }
}

