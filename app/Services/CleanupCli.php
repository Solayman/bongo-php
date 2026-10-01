<?php
namespace App\Services;
use app\Services\OCCleanup;
use utility\Input;
use utility\Message;
use utility\SystemOperation;

// Better autoloader
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Also try without namespace for root classes
    $simpleFile = __DIR__ . '/' . $class . '.php';
    if (file_exists($simpleFile)) {
        require_once $simpleFile;
    }
});



require_once 'OCCleanup.php';
require_once 'DatabaseCleaner.php';

class CleanupCli
{
    function getCliInput()
    {

        $choice = Input::getValidInput("Please select an option (1-3): ", ['1', '2', '3']);
        switch ($choice) {
            case 1:
                Message::showDatabaseMenu();;
                SystemOperation::clearScreen();
                SystemOperation::pressEnterToContinue();
            case 2:
                (new OCCleanup)->OcCleaninView();;
                SystemOperation::clearScreen();
                SystemOperation::pressEnterToContinue();
        }

    }

}

$cleanCli = new CleanupCli();
Message::showMainMenu();

$cleanCli->getCliInput();






