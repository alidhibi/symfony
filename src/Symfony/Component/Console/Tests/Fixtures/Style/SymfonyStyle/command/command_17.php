<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//Ensure symfony style helper methods handle trailing backslashes properly when decorating user texts
return static function (InputInterface $input, OutputInterface $output) : void {
    $output = new SymfonyStyle($input, $output);
    $output->title('Title ending with \\');
    $output->section('Section ending with \\');
};
