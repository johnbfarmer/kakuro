<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use AppBundle\Process\KakuroUniquenessTester;

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
        $a = '{"uiChoices":[{"choices":[],"is_editable":false,"is_data":false,"idx":0,"col":0,"row":0,"display":[0,0],"active":false,"strips":[],"semiactive":false},{"choices":[],"is_editable":false,"is_data":false,"idx":1,"col":1,"row":0,"display":[9,0],"active":false,"strips":[],"semiactive":false},{"choices":[],"is_editable":false,"is_data":false,"idx":2,"col":2,"row":0,"display":[9,0],"active":false,"strips":[],"semiactive":false},{"choices":[],"is_editable":false,"is_data":false,"idx":3,"col":3,"row":0,"display":[17,0],"active":false,"strips":[],"semiactive":false},{"choices":[],"is_editable":false,"is_data":false,"idx":4,"col":0,"row":1,"display":[0,11],"active":false,"strips":[],"semiactive":false},{"choices":[1],"is_editable":true,"is_data":true,"idx":5,"col":1,"row":1,"display":[0,0],"active":false,"strips":{"v":"1_1_v","h":"1_1_h"},"semiactive":false},{"choices":[2],"is_editable":true,"is_data":true,"idx":6,"col":2,"row":1,"display":[0,0],"active":false,"strips":{"h":"1_1_h","v":"1_2_v"},"semiactive":false},{"choices":[8],"is_editable":true,"is_data":true,"idx":7,"col":3,"row":1,"display":[0,0],"active":false,"strips":{"h":"1_1_h","v":"1_3_v"},"semiactive":false},{"choices":[],"is_editable":false,"is_data":false,"idx":8,"col":0,"row":2,"display":[0,24],"active":false,"strips":[],"semiactive":false},{"choices":[8],"is_editable":true,"is_data":true,"idx":9,"col":1,"row":2,"display":[0,0],"active":false,"strips":{"v":"1_1_v","h":"2_1_h"},"semiactive":false},{"choices":[7],"is_editable":true,"is_data":true,"idx":10,"col":2,"row":2,"display":[0,0],"active":false,"strips":{"v":"1_2_v","h":"2_1_h"},"semiactive":false},{"choices":[9],"is_editable":true,"is_data":true,"idx":11,"col":3,"row":2,"display":[0,0],"active":false,"strips":{"v":"1_3_v","h":"2_1_h"},"semiactive":false}],"height":3,"width":4}';

        $b = '{"uiChoices":[{"choices":[],"is_editable":false,"is_data":false,"idx":0,"col":0,"row":0,"display":[0,0],"strips":[]},{"choices":[],"is_editable":false,"is_data":false,"idx":1,"col":1,"row":0,"display":[11,0],"strips":[]},{"choices":[],"is_editable":false,"is_data":false,"idx":2,"col":2,"row":0,"display":[13,0],"strips":[]},{"choices":[],"is_editable":false,"is_data":false,"idx":3,"col":0,"row":1,"display":[0,9],"strips":[]},{"choices":[3],"is_editable":true,"is_data":true,"idx":4,"col":1,"row":1,"display":[0,0],"strips":{"h":"1_1_h","v":"1_1_v"}},{"choices":[6],"is_editable":true,"is_data":true,"idx":5,"col":2,"row":1,"display":[0,0],"strips":{"h":"1_1_h","v":"1_2_v"}},{"choices":[],"is_editable":false,"is_data":false,"idx":6,"col":0,"row":2,"display":[0,15],"strips":[]},{"choices":[8],"is_editable":true,"is_data":true,"idx":7,"col":1,"row":2,"display":[0,0],"strips":{"h":"2_1_h","v":"1_1_v"}},{"choices":[7],"is_editable":true,"is_data":true,"idx":8,"col":2,"row":2,"display":[0,0],"strips":{"h":"2_1_h","v":"1_2_v"}}],"height":3,"width":3}';
        
        // $parameters = json_decode($a, true);
        $parameters = json_decode($b, true);
        $t = KakuroUniquenessTester::autoExecute($parameters, null);
        $r = $t->getApiResponse();
        $output->writeln(json_encode($r));
        if ($r['hasUniqueSolution']) {
            $output->writeln(('wrong!!'));
        } else {
            $output->writeln(('correct'));
        }

    }

}
