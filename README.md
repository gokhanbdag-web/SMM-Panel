# SMM Panel — Modular Native PHP

PHP 8.1+ ve MySQL/MariaDB ile çalışır. Composer/SSH gerektirmez.

## Modüler yapı
- app/Core: PDO, oturum, CSRF ve uygulama yönlendirmesi
- app/Controllers: müşteri ve yönetim işlemleri
- app/Views: ayrı arayüz şablonları
- assets: açık renkli responsive tasarım
- database/schema.sql: veritabanı şeması

## Kurulum
MySQL veritabanı oluşturun, dosyaları web köküne yükleyin ve siteyi açın. install.php tarayıcıdan kurulum yapar, config.php oluşturur ve yönetici hesabını tanımlar.

## Entegrasyon notu
Bu commit modüler mimari geçişidir. Banka transfer bildirimleri ve yönetici onayı çalışır. Gerçek SMM sağlayıcı API'si, otomatik sipariş gönderimi, kart tahsilatı (PayTR/iyzico) ve callback doğrulaması bu depoda henüz tamamlanmış değildir; sağlayıcı hesabı/API bilgileri ve ödeme mağaza kimlik bilgileri olmadan gerçek uçtan uca entegrasyon test edilemez. Mevcut kod üretim için son güvenlik denetiminden geçirilmelidir.
