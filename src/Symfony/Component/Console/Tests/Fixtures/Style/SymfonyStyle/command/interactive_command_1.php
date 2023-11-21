<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//Ensure that questions have the expected outputs
return static function (InputInterface $input, OutputInterface $output) : void {
    $output = new SymfonyStyle($input, $output);
    $stream = fopen('php://memory', 'r+', false);
    fwrite($stream, "Foo\nBar\nBaz");
    rewind($stream);
    $input->setStream($stream);
    $output->ask("What's your name?");
    $output->ask('How are you?');
    $output->ask('Where do you come from?');
};
