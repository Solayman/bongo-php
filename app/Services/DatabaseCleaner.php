<?php
namespace App\Services;
use DateTime;

require_once 'DatabaseConnection.php';

class DatabaseCleaner
{
    private $pdo;
    private $tablesToTruncate = [
        'sale_integrators',
        'backup_sale_integrators',
        'sales',
        'backup_sales',
        'merchant_sales',
        'sale_billings',
        'backup_sale_billings',
        'transactionable',
        'transactionable_data',
        'backup_transactionable_data',
        'backup_success_transactionable_data',
        'rolling_balances',
        'tmp_sale_automations',
        'tmp_payment_records',
        'requests',
        'requests_data',
        'marketplace_sale_mappings',
        'sale_tax_info',
        'sale_error_details',
        'backup_sale_error_details',
        'refund_histories',
        'backup_requests_data',
        'backup_requests_data_backup',
        'wallet_logs',
        'archieved_wallet_logs',
        'backup_wallet_logs',
        'process_daily_settlement_uniques',
        'merchant_report_histories',
        'merchant_schedule_reports',
        'c2b_histories',
        'sends',
        'cashouts',
        'receives',
        'backup_transactionable',
        'notifications',
        'admin_notifications',
        'b2bpayment_histories',
        'backup_requests',
        'sale_reports',
        'backup_sale_reports',
        'sale_properties',
        'backup_sale_properties',
        'tmp_gov_btrans_report_data',
        'payment_reports',
        'backup_payment_reports',
        'payment_report_actions',
        'early_settlements',
        'process_sale_histories_uniques',
        'backup_process_sale_histories_uniques',
        'imported_transactions',
        'imported_transactions_data',
        'tmp_vat_report_data',
        'backup_success_bank_reference_informations',
        'backup_success_fp_wallet_transaction_entities',
        'backup_success_marketplace_item_mappings',
        'backup_success_marketplace_sales',
        'backup_success_marketplace_sale_mappings',
        'backup_success_merchant_sales',
        'backup_success_paid_bills',
        'backup_success_purchases',
        'backup_success_refund_histories',
        'backup_success_requests',
        'backup_success_rolling_balances',
        'backup_success_sales',
        'backup_success_sales_pf_records',
        'backup_success_sales_settlements',
        'backup_success_sale_billings',
        'backup_success_sale_currency_conversions',
        'backup_success_sale_error_details',
        'backup_success_sale_integrators',
        'backup_success_sale_points',
        'backup_success_sale_pos_redirections',
        'backup_success_sale_properties',
        'backup_success_sale_recurrings',
        'backup_success_sale_recurring_cards',
        'backup_success_sale_recurring_histories',
        'backup_success_sale_recurring_payment_schedules',
        'backup_success_sale_reports',
        'backup_success_sale_report_extras',
        'backup_success_sale_tax_info',
        'backup_success_sale_wallet_histories',
        'backup_success_transactionable',
        'backup_success_wix_state_datas',
        'backup_success_sale_summaries',
        'finalization_report_metadata',
        'finalization_reports',
        'finalization_report_actions',
        'backup_success_finalization_reports',
        'sale_billings',
        'refund_history_installments',
        'sale_asynchronous_processes',
        'incoming_wallet_events',
        'fp_wallet_transaction_entities',
        'tmp_refund_requests',
        'marketplace_item_mappings',
        'marketplace_sales',
        'sales_pf_records',
        'bank_reference_informations',
        'tmp_bank_responses',
        'sale_currency_conversions',
        'deposits',
        'card_token_requests',
        'sale_recurrings',
        'sale_recurring_histories',
        'sale_recurring_payment_schedules',
        'sale_recurring_cards',
        'sales_settlements',
        'tmp_sale_asyn_done',
        'tmp_payment_record_actions',
        'daily_sale_report',
        'sale_frauds',
        'backup_success_requests_data',
        'backup_success_requests_data_backup',
        'direct_payment_links',
        'payment_report_errors',
        'requests_data_backup',
        'tmp_gov_btrans_failed_file_records',
        'tmp_object_storage'
    ];

    public function __construct()
    {
        $this->pdo = DatabaseConnection::getInstance()->getConnection();
    }

    public function truncateTables()
    {
        echo "Starting table truncation...\n";
        
        foreach ($this->tablesToTruncate as $table) {
            try {
                $this->pdo->exec("TRUNCATE TABLE `$table`");
                echo "✓ Truncated: $table\n";
            } catch (\PDOException $e) {
                echo "✗ Failed to truncate $table: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Table truncation completed!\n";
    }

    public function deleteFromTables(array $tables)
    {
        echo "Starting data deletion...\n";
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("DELETE FROM `$table`");
                echo "✓ Deleted data from: $table\n";
            } catch (\PDOException $e) {
                echo "✗ Failed to delete from $table: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Data deletion completed!\n";
    }

    public function resetYapiTokens()
    {
        echo "Resetting yapi_tokens...\n";
        
        $todayMinus1 = (new DateTime())->modify('-1 day')->format('Y-m-d H:i:s');
        
        try {
            $this->pdo->exec("UPDATE yapi_tokens SET status = 0, attempt = 0, error_code = NULL, error_message = NULL, created_at = '$todayMinus1', updated_at = '$todayMinus1'");
            echo "✓ yapi_tokens reset successfully\n";
        } catch (\PDOException $e) {
            echo "✗ Failed to reset yapi_tokens: " . $e->getMessage() . "\n";
        }
    }

    public function resetTmpYapiReports()
    {
        echo "Resetting tmp_yapi_reports...\n";
        
        $today = (new DateTime())->format('Y-m-d H:i:s');
        
        try {
            $this->pdo->exec("UPDATE tmp_yapi_reports SET status = 0, created_at = '$today', updated_at = '$today'");
            echo "✓ tmp_yapi_reports reset successfully\n";
        } catch (\PDOException $e) {
            echo "✗ Failed to reset tmp_yapi_reports: " . $e->getMessage() . "\n";
        }
    }

    public function resetWallets()
    {
        echo "Resetting wallets...\n";
        
        $money = 0;
        
        try {
            $this->pdo->exec("UPDATE wallets SET amount = $money, non_withdrawable_amount = 0, withdrawable_amount = $money, rolling_amount = 0, pending_sale_amount = 0, block_amount = 0");
            echo "✓ wallets reset successfully\n";
        } catch (\PDOException $e) {
            echo "✗ Failed to reset wallets: " . $e->getMessage() . "\n";
        }
    }

    public function resetMerchants()
    {
        echo "Resetting merchants...\n";
        
        try {
            $this->pdo->exec("UPDATE merchants SET total_transaction = 0");
            echo "✓ merchants reset successfully\n";
        } catch (\PDOException $e) {
            echo "✗ Failed to reset merchants: " . $e->getMessage() . "\n";
        }
    }

    public function runFullCleanup()
    {
        echo "=== Starting Full Database Cleanup ===\n";
        
        $this->truncateTables();
        $this->resetYapiTokens();
        $this->resetTmpYapiReports();
        $this->resetWallets();
        $this->resetMerchants();
        
        echo "=== Database Cleanup Completed Successfully! ===\n";
    }

    public function getTableCount()
    {
        return count($this->tablesToTruncate);
    }
}