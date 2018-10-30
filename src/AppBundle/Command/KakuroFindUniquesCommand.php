<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use AppBundle\Process\KakuroUniqueFinder;

class KakuroFindUniquesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('kakuro:find-uniques')
            ->setDescription('...')
            ->addArgument(
                'size', InputArgument::OPTIONAL, 'heightxwidth'
            )
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'max number to test')
            ->addOption('start-index', 'i', InputOption::VALUE_REQUIRED, 'grids are ordered, specify where to start')
            ->addOption('show-next', 's', InputOption::VALUE_NONE, 'show next grid, do not store (for testing)')
            ->addOption('continue', 'c', InputOption::VALUE_NONE, 'continue from last library index')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parameters = array_merge($input->getArguments(), $input->getOptions());
        $parameters['output'] = $output;
        $doctrine = $this->getContainer()->get('doctrine');
        $parameters['doctrine'] = $doctrine;
        $size = $parameters['size'];
        $size_array = explode('x', $size);

        $parameters['height'] = $size_array[0];
        $parameters['width'] = $size_array[1];

        KakuroUniqueFinder::autoExecute($parameters, null);
    }

}
