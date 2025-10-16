<?php
namespace utility;
class Message
{
    public static function showMainMenu()
    {
        echo "\n";
        echo "==========================================\n";
        echo "           CLEANUP MANAGEMENT TOOL\n";
        echo "==========================================\n";
        echo "1. 🗃️  Database Cleanup\n";
        echo "2. 🧹 OC Cleanup (Views/Cache/Logs/Sessions)\n";
        echo "3. ❌ Exit\n";
        echo "==========================================\n";
    }
    public static function showOCMenu()
    {
        echo "\n";
        echo "==========================================\n";
        echo "           OC CLEANUP TOOL\n";
        echo "==========================================\n";
        echo "1. Clear View Cache\n";
        echo "2. Clear Config Cache\n";
        echo "3. Clear Log Files\n";
        echo "4. Clear Sessions\n";
        echo "5. Clear ALL (Complete Cleanup)\n";
        echo "6. Optimize Application\n";
        echo "7. Show System Info\n";
        echo "8. Back to Main Menu\n";
        echo "==========================================\n";
    }

    static function showDatabaseMenu()
    {
        echo "\n";
        echo "=====================================\n";
        echo "        DATABASE CLEANUP TOOL\n";
        echo "=====================================\n";
        echo "1. Full Cleanup (Truncate + Reset All)\n";
        echo "2. Truncate Tables Only\n";
        echo "3. Reset Wallets Only\n";
        echo "4. Reset Merchants Only\n";
        echo "5. Reset YAPI Tokens Only\n";
        echo "6. Reset TMP YAPI Reports Only\n";
        echo "7. Show Table Count\n";
        echo "8. Back to Main Menu\n";
        echo "=====================================\n";
    }
}