<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// ensure long words are properly wrapped in blocks
return static function (InputInterface $input, OutputInterface $output) : void {
    $word = 'Lopadotemachoselachogaleokranioleipsanodrimhypotrimmatosilphioparaomelitokatakechymenokichlepikossyphophattoperisteralektryonoptekephalliokigklopeleiolagoiosiraiobaphetraganopterygon';
    $sfStyle = new SymfonyStyle($input, $output);
    $sfStyle->block($word, 'CUSTOM', 'fg=white;bg=blue', ' § ', false);
};
