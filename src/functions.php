<?php

use App\Colors;

function cprintf($color = Colors::RESET_ALL, $format = '', ...$args)
{
    echo 
        new DateTime()->format('Y-m-d H:i:s.u> '),
        $color,
        sprintf($format, ...$args),
        Colors::RESET_ALL,
        PHP_EOL;  
}