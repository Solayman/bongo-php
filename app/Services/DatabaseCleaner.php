<?php

namespace App\Services;

use System\Database\DB;
use DateTime;

class DatabaseCleaner
{
    protected $db;

    protected array $tablesToTruncate = [
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
        // DB is your wrapper class that returns QueryBuilder
        $this->db = new DB();
    }

    public function truncateTables(): void
    {
        echo "🚀 Starting table truncation...\n";

        foreach ($this->tablesToTruncate as $table) {
            try {
                $this->db->table($table)->truncate();
                echo "✅ Truncated: $table\n";
            } catch (\Throwable $e) {
                echo "❌ Failed to truncate $table: " . $e->getMessage() . "\n";
            }
        }

        echo "🎉 Table truncation completed!\n";
    }

    public function resetYapiTokens(): void
    {
        echo "🔁 Resetting yapi_tokens...\n";

        $todayMinus1 = (new DateTime())->modify('-1 day')->format('Y-m-d H:i:s');

        try {
            $this->db->table('yapi_tokens')->update([
                'status' => 0,
                'attempt' => 0,
                'error_code' => null,
                'error_message' => null,
                'created_at' => $todayMinus1,
                'updated_at' => $todayMinus1,
            ]);
            echo "✅ yapi_tokens reset successfully\n";
        } catch (\Throwable $e) {
            echo "❌ Failed to reset yapi_tokens: " . $e->getMessage() . "\n";
        }
    }

    public function resetTmpYapiReports(): void
    {
        echo "🔁 Resetting tmp_yapi_reports...\n";

        $now = (new DateTime())->format('Y-m-d H:i:s');

        try {
            $this->db->table('tmp_yapi_reports')->update([
                'status' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            echo "✅ tmp_yapi_reports reset successfully\n";
        } catch (\Throwable $e) {
            echo "❌ Failed to reset tmp_yapi_reports: " . $e->getMessage() . "\n";
        }
    }

    public function resetWallets(): void
    {
        echo "💰 Resetting wallets...\n";

        try {
            $this->db->table('wallets')->update([
                'amount' => 0,
                'non_withdrawable_amount' => 0,
                'withdrawable_amount' => 0,
                'rolling_amount' => 0,
                'pending_sale_amount' => 0,
                'block_amount' => 0,
            ]);
            echo "✅ wallets reset successfully\n";
        } catch (\Throwable $e) {
            echo "❌ Failed to reset wallets: " . $e->getMessage() . "\n";
        }
    }

    public function resetMerchants(): void
    {
        echo "🏪 Resetting merchants...\n";

        try {
            $this->db->table('merchants')->update([
                'total_transaction' => 0
            ]);
            echo "✅ merchants reset successfully\n";
        } catch (\Throwable $e) {
            echo "❌ Failed to reset merchants: " . $e->getMessage() . "\n";
        }
    }

    public function runFullCleanup(): void
    {
        echo "🧹=== Starting Full Database Cleanup ===\n";

        $this->truncateTables();
        $this->resetYapiTokens();
        $this->resetTmpYapiReports();
        $this->resetWallets();
        $this->resetMerchants();

        echo "🎯=== Database Cleanup Completed Successfully ===\n";
    }

    public function getTableCount(): int
    {
        return count($this->tablesToTruncate);
    }

    public function getTableList(): array
    {
        return $this->tablesToTruncate;
    }
}
