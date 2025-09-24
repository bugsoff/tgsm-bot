<?php
// Colors.php

namespace App;

class Colors
{
    public const BLACK       = "\033[0;30m";  # Black
    public const RED         = "\033[0;31m";  # Red
    public const GREEN       = "\033[0;32m";  # Green
    public const YELLOW      = "\033[0;33m";  # Yellow
    public const BLUE        = "\033[0;34m";  # Blue
    public const PURPLE      = "\033[0;35m";  # Purple
    public const CYAN        = "\033[0;36m";  # Cyan
    public const WHITE       = "\033[0;37m";  # White
    public const RESET       = "\033[0m";     # Text Reset

    public const BOLD_BLACK  = "\033[1;30m";  # Black
    public const BOLD_RED    = "\033[1;31m";  # Red
    public const BOLD_GREEN  = "\033[1;32m";  # Green
    public const BOLD_YELLOW = "\033[1;33m";  # Yellow
    public const BOLD_BLUE   = "\033[1;34m";  # Blue
    public const BOLD_PURPLE = "\033[1;35m";  # Purple
    public const BOLD_CYAN   = "\033[1;36m";  # Cyan
    public const BOLD_WHITE  = "\033[1;37m";  # White

    public const BG_BLACK    = "\033[40m";    # Black
    public const BG_RED      = "\033[41m";    # Red
    public const BG_GREEN    = "\033[42m";    # Green
    public const BG_YELLOW   = "\033[43m";    # Yellow
    public const BG_BLUE     = "\033[44m";    # Blue
    public const BG_PURPLE   = "\033[45m";    # Purple
    public const BG_CYAN     = "\033[46m";    # Cyan
    public const BG_WHITE    = "\033[47m";    # White

}

function cprintf($color, $format, ...$args)
{
    echo 
        defined(Colors::$color) ?: Colors::RESET,
        sprintf(date("c") . $format . PHP_EOL, $args),
        Colors::RESET;  
}
