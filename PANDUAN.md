# SlotKita — Panduan

Platform tempahan online untuk kedai servis (barber, salon, spa, car wash, klinik, studio).
**Satu sistem, banyak kedai.** Setiap kedai daftar sendiri, dapat pautan `slotkita.my/nama-kedai`,
dan bayar langganan bulanan **kepada anda**.

---

## 1. Macam mana sistem ini jana duit

| Pelan | Harga | Had |
|---|---|---|
| Percuma | RM0 | 30 tempahan online/bulan, 1 staf, ada label "Dikuasakan oleh SlotKita" |
| Asas | RM39/bulan | 300 tempahan, 3 staf, Telegram, warna & gambar sendiri |
| Pro | RM79/bulan | tanpa had, 15 staf, tanpa label SlotKita |

- Kedai baru dapat **14 hari Pro percuma**, lepas itu turun ke Percuma (bukan dikunci). Kedai tak lari, dan
  bila kedai makin sibuk, had 30 tempahan itulah yang mendorong mereka naik taraf.
- Bayaran tahunan = bayar 10 bulan (2 bulan percuma), jadi duit tunai masuk awal.
- **Pertumbuhan percuma:** setiap halaman kedai Percuma ada label "Dikuasakan oleh SlotKita" yang
  membawa kod rujukan. Kedai yang jemput kedai lain dapat 30 hari percuma bila kedai itu membayar.
- **Panel admin → Ringkasan → "Susulan"** menyenaraikan kedai yang percubaannya hampir tamat, pelan hampir
  luput, atau hampir capai had. Mesej WhatsApp sudah siap ditulis, jadi anda cuma tekan dan hantar.
  Ini senarai kerja jualan harian anda.

Tukar harga/had di **satu tempat sahaja**: `lib/plans.php` (`PLANS`, `PERIODS`).

### Anggaran jujur

| Kedai berbayar (Asas) | Pendapatan sebulan |
|---|---|
| 10 | RM390 |
| 50 | RM1,950 |
| 150 | RM5,850 |

Kos: hosting ~RM10–30/bulan, domain ~RM50/tahun, caj FPX ToyyibPay setiap transaksi.
**Tiada jaminan.** Sistem ini alat; duit datang bila anda jumpa kedai dan tunjuk nilainya (lihat bahagian 7).

---

## 2. Jalankan di komputer sendiri

1. Cipta pangkalan data `slotkita` (MySQL 8, port 3306) dan import `database/install.sql`.
2. Dari dalam folder projek, jalankan:
   ```
   php -S localhost:8120 router.php
   ```
3. Buka http://localhost:8120

Akaun ujian (komputer sendiri SAHAJA):
- Admin platform: `admin@slotkita.test` / `admin12345`
- Pemilik kedai demo: `demo@slotkita.test` / `demo1234` (http://localhost:8120/demo)

Bayaran dalam mod **mock** (tiruan): butang FPX buka halaman ujian dan tiada duit sebenar berpindah.

---

## 3. Pasang di hosting (cPanel)

1. **Beli domain + hosting** yang ada PHP 8.1+ dan MySQL/MariaDB (cth Exabytes, Shinjiru, Hostinger).
2. **cPanel → MySQL Databases:** cipta pangkalan data + pengguna, beri ALL PRIVILEGES.
3. **phpMyAdmin → Import** `database/install.sql`.
4. **Muat naik** semua fail folder `slotkita` ke `public_html` (KECUALI `config.php`).
5. Salin `config.sample.php` → `config.php`, dan isi:
   - `env` => `production`
   - `app_url` => `https://domain-anda.my`
   - maklumat DB, `support_whatsapp`, `support_email`, `bank`
6. **SSL:** cPanel → SSL/TLS Status → Run AutoSSL (percuma). Sistem memaksa https dalam production.
7. **cPanel → Terminal** (atau SSH), di folder laman:
   ```
   php tools/buat-admin.php emel@anda.com KataLaluanKuat "Nama Anda"
   php tools/seed-demo.php
   ```
   `seed-demo.php` cipta kedai contoh `/demo` untuk laman utama (kata laluan rawak dipapar sekali).
8. Pastikan folder `storage/` dan `uploads/` boleh ditulis (permission 755).

Tiada cron job diperlukan kerana status pelan dikira terus setiap kali halaman dibuka.

---

## 4. ToyyibPay (terima bayaran FPX, akaun individu guna IC)

1. Daftar di https://toyyibpay.com sebagai **Individu** dan lengkapkan pengesahan akaun bank.
2. Dashboard → **Settings → User Secret Key**, salin ke `config.php` → `toyyibpay.secret_key`.
3. Cipta kategori bil sekali:
   ```
   php tools/toyyibpay-kategori.php
   ```
   Salin `CategoryCode` ke `toyyibpay.category_code`.
4. Tukar `payment_driver` => `toyyibpay` dan `sandbox` => `false`.
5. Uji dengan membayar RM39 sendiri (guna akaun kedai ujian), dan pastikan pelan aktif serta-merta.

> Nak uji dahulu tanpa duit sebenar? Daftar di https://dev.toyyibpay.com, guna kunci sandbox dan `sandbox => true`.

Keselamatan bayaran yang sudah dibina:
- Status bayaran **tidak dipercayai dari URL**. Sistem sentiasa tanya ToyyibPay terus (`getBillTransactions`).
- Callback disahkan dengan hash `md5(secret + status + order_id + refno + "ok")`.
- Jumlah mesti sama dengan bil; jika berbeza, bayaran ditahan untuk semakan manual.
- Bayaran yang sama tidak boleh mengaktifkan langganan dua kali.

**Pindahan bank manual** juga tersedia: kedai muat naik resit → **Admin → Bayaran** → semak bank anda → Luluskan.
Resit disimpan dalam `storage/receipts` (tidak boleh dibuka oleh orang awam).

---

## 5. Notifikasi Telegram (percuma)

1. Di Telegram, cari **@BotFather** → `/newbot` → beri nama (cth `SlotKita Bot`) → dapat **token**.
2. `config.php` → `telegram`:
   - `bot_token` => token itu
   - `bot_username` => nama bot tanpa @
   - `webhook_secret` => rentetan rawak panjang
   - `admin_chat_id` => chat ID anda (hantar mesej ke @userinfobot untuk dapatkan)
3. Selepas laman di https:
   ```
   php tools/telegram-webhook.php
   ```
4. Pemilik kedai: Panel → Notifikasi → **Sambung Telegram** → tekan START.

Anda (admin) akan dapat mesej setiap kali ada kedai baru, bayaran masuk, atau resit perlu disemak.

---

## 6. Senarai semak sebelum lancar

- [ ] Tukar nama `SlotKita` jika mahu (config `app_name`) dan semak nama itu belum digunakan orang lain.
- [ ] `config.php`: `env=production`, `app_url` https, DB, bank, WhatsApp sokongan.
- [ ] Baca & sesuaikan `terma.php` (terma + dasar privasi PDPA). Ini draf asas, bukan nasihat undang-undang.
- [ ] ToyyibPay sebenar diuji dengan satu bayaran kecil.
- [ ] Bot Telegram disambung.
- [ ] Kata laluan admin kuat (bukan `admin12345`).
- [ ] Backup: cPanel → Backup → muat turun pangkalan data seminggu sekali.

---

## 7. Dapatkan 10 kedai pertama

Sistem tak akan jual dirinya sendiri pada awal. Cara yang paling cepat berhasil:

1. **Mula dengan orang yang anda kenal.** Kedai yang anda pernah bina sistem untuknya (cth Tokdin Barbershop)
   sudah percaya pada anda. Tawarkan SlotKita kepada mereka dahulu dan minta izin guna nama mereka sebagai contoh.
2. **Datang ke kedai, bukan hantar mesej.** Buka `/demo` di telefon, biar tuan kedai cuba tempah sendiri
   dalam 30 saat, kemudian daftarkan kedai mereka **di depan mereka** (5 minit) dan cetak poster QR.
3. **Target:** kedai yang telefonnya selalu berbunyi, ada 2+ staf, dan aktif di Instagram/TikTok.
4. **Susulan hari ke-10:** buka Admin → Ringkasan → Susulan, dan WhatsApp mereka sebelum percubaan tamat.
5. **Minta testimoni sebenar** (dengan izin) selepas sebulan. Jangan sekali-kali reka testimoni.
6. Bila ada 5 kedai gembira, beri setiap satu pautan jemputan mereka (Panel → Kongsi → "Ajak kedai lain").

---

## 8. Struktur fail

```
index.php            laman pemasaran (harga, soalan lazim)
daftar.php masuk.php kedai.php (halaman tempahan awam /slug) semak.php terma.php
app/                 panel pemilik kedai
admin/               panel anda (admin platform)
api/                 slot, tempah, bayaran (ToyyibPay), webhook Telegram
lib/                 enjin tempahan, pelan & bil, gerbang bayaran, keselamatan
tools/               skrip terminal (buat admin, demo, ToyyibPay, Telegram, reset kata laluan)
database/install.sql skema penuh
```

Terkunci kata laluan? `php tools/reset-password.php emel@anda.com KataLaluanBaru`
