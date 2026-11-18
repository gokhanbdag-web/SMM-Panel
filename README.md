# SMM Panel — Modular Native PHP

PHP 8.1+ ve MySQL/MariaDB ile çalışır. Composer veya SSH gerektirmez. Tarayıcı tabanlı kurulum ve açık renkli responsive arayüz içerir.

## Kurulum ve güncelleme
Dosyaları web köküne yükleyin ve siteyi açın. İlk kurulum `install.php` üzerinden yapılır. Mevcut kurulumlarda uygulama açıldığında `app/Core/Schema.php` eksik tabloları/kolonları güvenli biçimde ekler; kullanıcı, sipariş ve bakiye kayıtları silinmez.

## Sağlayıcıdan bağımsız API
Yaygın SMM Panel API protokolü (`action=add`, `action=status`, `action=balance`) desteklenir. Yönetim panelinden birden fazla sağlayıcı eklenebilir; API anahtarları AES-256-GCM ile şifreli saklanır. Servisler sağlayıcı servis ID'sine bağlanır. Sağlayıcıya bağlanmamış hizmet siparişleri manuel inceleme kuyruğuna alınır; gerçek gönderim yapılmış gibi gösterilmez.

## Otomatik sipariş işçisi
Sunucuda cron ile dakikada bir çalıştırın:
```bash
* * * * * /usr/local/lsws/lsphp85/bin/lsphp /home/ACCOUNT/public_html/cron/process-orders.php >/dev/null 2>&1
```
PHP binary ve dosya yolunu sunucunuza göre uyarlayın. Worker queued siparişleri sağlayıcıya iletir, durumları sorgular ve başarısız/iptal edilen siparişlerde bakiyeyi tek sefer iade eder. Sağlayıcı hesabı ve geçerli API bilgisi olmadan gerçek sipariş testi yapılamaz.

## Yönetim
- Sağlayıcı ekleme, düzenleme, aktif/pasif ve API bağlantı testi
- Hizmet ekleme, fiyat/miktar aralığı ve sağlayıcı servisine eşleme
- Sipariş durum yönetimi ve sağlayıcı işlem kayıtları
- Kullanıcı aktif/pasif yönetimi
- Banka/IBAN ve site adı ayarları, EFT bildirimi onayı

## Sınırlar
Kartlı tahsilat için PayTR/iyzico mağaza bilgileri ve doğrulanmış callback entegrasyonu gerekir; bu bilgiler olmadan kart ödemesi etkinleştirilmez. Sağlayıcı callback endpoint'i yerine güvenli cron tabanlı durum sorgulama kullanılır. HTTPS zorunludur. `config.php`, `install.lock`, `app/`, `database/`, `cron/` ve `tests/` web üzerinden erişilemez.
