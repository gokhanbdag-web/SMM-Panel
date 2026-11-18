# Production deployment checklist

- PHP 8.1+ with PDO MySQL, cURL, OpenSSL, JSON and mbstring.
- MySQL 8+ / MariaDB with InnoDB and utf8mb4.
- Force HTTPS; set secure session cookies; disable display_errors.
- Keep config.php and deploy-config.php outside public access and make backups.
- Configure provider API endpoint and key in the admin area. Provider endpoints must use HTTPS.
- Configure PayTR merchant credentials in the admin area; verify callbacks with the official signature algorithm before crediting a wallet.
- Set a cron job for order dispatch and status polling. Do not rely on browser requests for background work.
- Test provider add/status/balance and payment callbacks in sandbox before enabling production.
- Review local laws and each social network's terms before offering engagement services.
