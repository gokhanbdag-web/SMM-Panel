# SMM-Panel

Native PHP + MySQL tabanlı, Composer ve SSH gerektirmeyen başlangıç sürümü.

## Özellikler
- İlk ziyaret sırasında otomatik kurulum sayfasına yönlendirme.
- Tarayıcıdan MySQL ve yönetici hesabı tanımlama.
- Üyelik, giriş, bakiye, EFT bildirimi, sipariş ve sipariş geçmişi.
- Yönetici tarafından EFT taleplerini onaylama/reddetme.
- Örnek sosyal medya hizmetleri.

## Kurulum
1. Hosting üzerinde boş bir MySQL veritabanı ve kullanıcı oluşturun.
2. Bu deponun dosyalarını web kök dizinine FTP ile gönderin.
3. Alan adınızı açın. Kurulu değilse otomatik olarak `install.php` sayfasına yönlenir.
4. Veritabanı ve yönetici hesabı bilgilerini girin.
5. Kurulum tamamlanınca giriş sayfası açılır. `config.php` web erişimine .htaccess ile kapatılır.

## GitHub Actions ile FTP dağıtımı
Depo ayarlarında **Settings → Secrets and variables → Actions** altında şu Repository secrets değerlerini tanımlayın:
- `FTP_SERVER`: hosting FTP sunucusu (hostname)
- `FTP_USERNAME`: FTP kullanıcı adı
- `FTP_PASSWORD`: FTP parolası
- `FTP_SERVER_DIR`: hedef dizin (ör. `/public_html/` veya hostinginizin verdiği tam FTP yolu)

Ardından `main` dalına gönderilen her commit otomatik olarak FTP ile aktarılır. İlk aktarımda FTP_SERVER_DIR değerinin doğru web kök dizinini gösterdiğinden emin olun. Bu workflow FTP kullanır; hosting FTPES/FTPS gerektiriyorsa protokol ayarı ayrıca değiştirilmelidir.

## Sınırlamalar
- Online ödeme bölümü şimdilik yalnızca bekleyen kayıt oluşturur; karttan para çekmez. PayTR/iyzico entegrasyonu ve imza doğrulamalı callback olmadan gerçek tahsilat etkin değildir.
- SMM sağlayıcı API'si bağlanmadığından siparişler otomatik teslim edilmez; manuel yönetim gerektirir.
- Canlı kullanım öncesinde HTTPS, hosting yedeklemesi, güvenlik testi ve gerçek ödeme/sağlayıcı entegrasyonları tamamlanmalıdır.
