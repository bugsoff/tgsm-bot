<?php

use App\Colors;

function cprintf($color = Colors::RESET, $format = '', ...$args)
{
    echo 
        new DateTime()->format('Y-m-d H:i:s.u> '),
        $color,
        sprintf($format, ...$args),
        Colors::RESET,
        Colors::BG_BLACK,
        PHP_EOL;  
}