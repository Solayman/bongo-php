<?php
namespace utility;
class SystemOperation
{
    static function clearScreen()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system('cls');
        } else {
            system('clear');
        }
    }
    static function pressEnterToContinue()
    {
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
}