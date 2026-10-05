# Teknik Destek Scripti

PHP + MySQL + HTML + JavaScript ile yazılmış, üyeliksiz, erişilebilir (WCAG odaklı) teknik destek sistemi. E-posta kullanılmaz; tüm bildirimler Telegram botları ile gönderilir.

## Özellikler
- Herkes talep açabilir (üyelik yok). Talep açarken Telegram bot tokeni ve chat ID **zorunludur**; bota doğrulama mesajı gönderilemezse talep oluşturulmaz.
- Talep numarası (`TD-XXXXXXXX`) ile takip sayfası (`track.php`), kullanıcı ek mesaj yazabilir.
- Yeni talep / kullanıcı yanıtı yönetici botuna, yönetici yanıtı talep sahibinin botuna bildirilir.
- Yönetim paneli (`admin/`): talep arama (numara / konu), durum filtresi, yanıtlama, site ayarları ve hizmet (destek ayarları) yönetimi.
- CSRF koruması, PDO hazır sorgular, çıktı kaçışı, parola hash'leme.

## Kurulum
1. Boş bir MySQL veritabanı oluşturun.
2. Dosyaları PHP 8+ (curl, pdo_mysql) sunucusuna yükleyin.
3. `install.php` sayfasını açın, bilgileri girin; sonra `install.php` dosyasını silin.
4. `admin/login.php` ile giriş yapın; Ayarlar'dan yönetici bot tokeni/chat ID ve hizmetleri ekleyin.

Not: Kullanıcının bot tokeni yanıt bildirimi gönderebilmek için veritabanında saklanır.
