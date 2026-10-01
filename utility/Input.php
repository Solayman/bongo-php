<?php
namespace utility;
class Input
{
   static function getValidInput($prompt, $validOptions)
    {
        while (true) {
            echo $prompt;
            $input = trim(fgets(STDIN));

            if (in_array($input, $validOptions)) {
                return $input;
            }

            echo "Invalid option! Please try again.\n\n";
        }
    }
}