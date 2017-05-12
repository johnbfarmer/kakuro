<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

// use Entity\TestEntity;
use AppBundle\Process\BuildGrid;

class BuildGridCommand extends Command
{
    protected 
        $em,
        $parameters;

    protected function configure()
    {
        $this->setName('kakuro:build')
            ->setDescription('x')
            ->setHelp('y')
            ->addArgument(
                'size', InputArgument::REQUIRED, 'heightxwidth'
            )
            ->addOption(
                'density', 'd', InputOption::VALUE_REQUIRED, '0<x<1'
            )
            ->addOption(
                'browser', 'b', InputOption::VALUE_NONE, '0<x<1'
            )
            ->addOption(
                'symmetry', 's', InputOption::VALUE_NONE, '0<x<1'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('CREATING KAKURO');
        $parameters = array_merge($input->getArguments(), $input->getOptions());
        $size = $parameters['size'];
        $size_array = explode('x', $size);
        if (count($size_array) !== 2) {
            throw new \Exception('Size argument "' . $size . '" must be heightxwidth, like 12x12');
            
        }

        $parameters['height'] = $size_array[0];
        $parameters['width'] = $size_array[1];
        BuildGrid::autoExecute($parameters, null);
    }
}

