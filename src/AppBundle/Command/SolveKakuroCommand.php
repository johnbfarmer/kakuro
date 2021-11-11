<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use AppBundle\Process\KakuroSolver;

class SolveKakuroCommand extends ContainerAwareCommand
{
    protected 
        $em,
        $parameters;

    protected function configure()
    {
        $this->setName('kakuro:solve')
            ->setDescription('x')
            ->setHelp('y')
            ->addOption(
                'id', '', InputOption::VALUE_REQUIRED, ''
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // $output->writeln('KAKURO TABLES SOLVER');
        $parameters = array_merge($input->getArguments(), $input->getOptions());
        $em = $this->getContainer()->get('doctrine')->getManager();
        $grid = $em->getRepository('AppBundle:Grid')->find($parameters['id']);
        $parameters = [
            'grid' => $grid,
            'cells' => $grid->getForProcessing(),
            'simpleReduction' => false,
        ];
        $t = KakuroSolver::autoExecute($parameters, null);
        $r = $t->getResult();
        $u = $t === 'nonunique' ? 0 : 1;
        print $grid->getId() . ', ' . $u . ',' . $r;
    }
}

