# SMM-Panel

Native PHP + MySQL tabanlı, Composer ve SSH gerektirmeyen başlangıç sürümü.

## Özellikler
- İlk ziyaret sırasında otomatik kurulum sayfasına yönlendirme.
- Tarayıcıdan MySQL ve yönetici hesabı tanımlama.
- Üyelik, giriş, bakiye, EFT bildirimi, sipariş ve sipariş geçmişi.
- Yönetici tarafından EFT taleplerini onaylama/reddetme.
- Örnek sosyal medya hizmetleri.

## İlk kurulum
1. Hosting üzerinde boş bir MySQL veritabanı ve kullanıcı oluşturun.
2. Depo dosyalarını web kök dizinine ilk kez yükleyin.
3. Alan adınızı açın; kurulu değilse `install.php` otomatik açılır.
4. Veritabanı ve yönetici hesabı bilgilerini girin.
5. Kurulumdan sonra `config.php` oluşturulur.

## GitHub Webhook ile otomatik dağıtım (FTP/SSH yok)
1. Depodaki dosyaları ilk kurulum için hosting web kök dizinine bir kez yükleyin.
2. Hosting dosya yöneticisinden, `deploy.php` ile aynı klasöre `deploy-config.php` oluşturun. İçeriği:
   ```php
   <?php
   return [
     'webhook_secret' => 'BURAYA_UZUN_RASTGELE_GIZLI_ANAHTAR',
     'github_token' => '', // Depo private ise repo içeriğini okuyabilen fine-grained token
   ];
   ```
   Gizli anahtarı kendiniz üretin (en az 32 rastgele karakter). Bu dosyayı GitHub'a yüklemeyin.
3. GitHub deposunda **Settings → Webhooks → Add webhook**:
   - Payload URL: `https://SITENIZ.COM/deploy.php`
   - Content type: `application/json`
   - Secret: deploy-config.php içindeki webhook_secret ile aynı değer
   - Which events: **Just the push event**
   - Active: açık
4. Depo public ise github_token boş kalabilir. Depo private ise yalnızca bu depoya Contents: Read izni olan fine-grained personal access token ekleyin.
5. **Add webhook** ile kaydedin. GitHub'da webhook sayfasındaki Recent Deliveries bölümünden ilk testin sonucunu kontrol edin.
6. Bundan sonra `main` dalına yapılan her push, webhook üzerinden dağıtımı başlatır.

Dağıtım, mevcut `config.php`, `deploy-config.php`, `uploads/` ve `storage/` öğelerini korur. Hosting PHP cURL ve ZipArchive uzantılarını desteklemelidir. `deploy-config.php` dosyasını kesinlikle depoya commit etmeyin; sunucuda tutun. Webhook endpoint'i yalnızca imzası doğru, doğru depodan ve main dalından gelen push olaylarını kabul eder.

## Sınırlamalar
- Online ödeme bölümü şimdilik yalnızca bekleyen kayıt oluşturur; karttan para çekmez. PayTR/iyzico entegrasyonu ve imza doğrulamalı callback olmadan gerçek tahsilat etkin değildir.
- SMM sağlayıcı API'si bağlanmadığından siparişler otomatik teslim edilmez; manuel yönetim gerektirir.
- Canlı kullanım öncesinde HTTPS, hosting yedeklemesi, güvenlik testi ve gerçek ödeme/sağlayıcı entegrasyonları tamamlanmalıdır.
