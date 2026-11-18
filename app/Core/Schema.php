<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
final class Schema
{
 public static function migrate(): void {
  $db=Database::pdo();
  $db->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value MEDIUMTEXT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS providers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,api_url VARCHAR(500) NOT NULL,api_key TEXT NOT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS provider_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_id BIGINT UNSIGNED NULL,order_id BIGINT UNSIGNED NULL,level VARCHAR(20) NOT NULL,message VARCHAR(1000) NOT NULL,created_at DATETIME NOT NULL,INDEX(provider_id),INDEX(order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  self::column($db,'services','provider_id','BIGINT UNSIGNED NULL');
  self::column($db,'services','provider_service_id','VARCHAR(100) NULL');
  self::column($db,'services','refill_enabled','TINYINT(1) NOT NULL DEFAULT 0');
  self::column($db,'services','cancel_enabled','TINYINT(1) NOT NULL DEFAULT 0');
  self::column($db,'orders','provider_id','BIGINT UNSIGNED NULL');
  self::column($db,'orders','provider_order_id','VARCHAR(120) NULL');
  self::column($db,'orders','start_count','VARCHAR(40) NULL');
  self::column($db,'orders','remains','VARCHAR(40) NULL');
  self::column($db,'orders','last_check_at','DATETIME NULL');
  self::column($db,'orders','updated_at','DATETIME NULL');
  self::column($db,'orders','error_message','VARCHAR(500) NULL');
  self::column($db,'orders','refunded_at','DATETIME NULL');
  $db->prepare("INSERT IGNORE INTO settings(setting_key,setting_value,updated_at) VALUES('bank_name','',NOW()),('bank_iban','',NOW()),('bank_account_name','',NOW()),('site_name','SMM Panel',NOW()),('currency','TRY',NOW())")->execute();
  $db->prepare("INSERT IGNORE INTO schema_migrations(version,applied_at) VALUES('2026_11_smm_core',NOW())")->execute();
 }
 private static function column(PDO $db,string $table,string $column,string $definition):void {
  $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $q->execute([$table,$column]);
  if(!(int)$q->fetchColumn()) $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
 }
}
