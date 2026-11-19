<?php
require __DIR__ . '/bootstrap.php';
require ROOT . '/lib/shop.php';
capture_referral();

$title = cfg('app_name') . ' — Tempahan online untuk kedai anda';
$hasDemo = (bool)val("SELECT 1 FROM shops WHERE slug = 'demo'");
$trialDays = (int)cfg('trial_days', 14);
include ROOT . '/includes/site_head.php';
?>
<main>
  <!-- ============ HERO ============ -->
  <section class="hero">
    <div class="wrap hero-in">
      <div class="hero-copy">
        <span class="eyebrow"><?= icon('scissors', 'ic-sm') ?> Barber · Salon · Spa · Car wash</span>
        <h1>Pelanggan tempah sendiri.<br><span class="hl">Anda terus buat kerja.</span></h1>
        <p class="lead">Dapat pautan tempahan kedai anda sendiri. Pelanggan pilih servis, staf dan masa, 24 jam sehari. Tak perlu lagi balas mesej <em>"ada slot tak bang?"</em> satu per satu.</p>
        <div class="hero-cta">
          <a class="btn btn-brand btn-lg" href="daftar.php">Cuba percuma <?= $trialDays ?> hari <?= icon('arrow', 'ic-sm') ?></a>
          <?php if ($hasDemo): ?><a class="btn btn-ghost btn-lg" href="demo" target="_blank" rel="noopener">Tengok demo</a><?php endif; ?>
        </div>
        <ul class="hero-trust">
          <li><?= icon('check', 'ic-sm') ?> Tiada kad kredit</li>
          <li><?= icon('check', 'ic-sm') ?> Siap dalam 5 minit</li>
          <li><?= icon('check', 'ic-sm') ?> Tiada caj automatik</li>
        </ul>
      </div>
      <div class="hero-phone" aria-hidden="<?= $hasDemo ? 'false' : 'true' ?>">
        <div class="url-chip"><?= icon('link', 'ic-sm') ?> <?= e(preg_replace('#^https?://#', '', url(''))) ?><b>kedai-anda</b></div>
        <div class="phone">
          <div class="phone-notch"></div>
          <?php if ($hasDemo): ?>
            <iframe src="demo?embed=1" title="Contoh halaman tempahan" loading="eager" tabindex="-1"></iframe>
          <?php endif; ?>
        </div>
        <div class="float-note n1"><span class="tg"><?= icon('send', 'ic-sm') ?></span><div><b>Tempahan baru #K7PX2A</b><small>Hafiz · Fade · Sabtu 3:00 PTG</small></div></div>
        <div class="float-note n2"><span class="ok"><?= icon('check', 'ic-sm') ?></span><div><b>Slot 3:00 PTG diambil</b><small>Tiada tempahan bertindih</small></div></div>
      </div>
    </div>
  </section>

  <!-- ============ SEBELUM / SELEPAS ============ -->
  <section class="section compare-sec">
    <div class="wrap">
      <div class="compare">
        <div class="col before">
          <h3>Tanpa sistem tempahan</h3>
          <ul>
            <li>Telefon berbunyi masa tengah gunting</li>
            <li>Balas WhatsApp sampai pukul 12 malam</li>
            <li>Dua pelanggan datang untuk slot sama</li>
            <li>Pelanggan pergi kedai lain sebab lambat dibalas</li>
          </ul>
        </div>
        <div class="col after">
          <h3>Dengan <?= e(cfg('app_name')) ?></h3>
          <ul>
            <li>Pelanggan nampak slot kosong dan tempah sendiri</li>
            <li>Notifikasi terus ke Telegram anda</li>
            <li>Sistem kunci slot, jadi tak mungkin bertindih</li>
            <li>Tempahan masuk walaupun kedai dah tutup</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ CIRI ============ -->
  <section class="section" id="ciri">
    <div class="wrap">
      <div class="sec-head">
        <span class="kicker">Ciri</span>
        <h2>Semua yang kedai servis perlukan.<br>Tak ada yang tak perlu.</h2>
      </div>
      <div class="features">
        <article class="feat">
          <span class="f-ic"><?= icon('link') ?></span>
          <h3>Pautan kedai sendiri</h3>
          <p>Letak di bio Instagram, TikTok, Google Maps dan status WhatsApp. Pelanggan tak perlu muat turun app atau buat akaun.</p>
        </article>
        <article class="feat">
          <span class="f-ic"><?= icon('shield') ?></span>
          <h3>Tiada tempahan bertindih</h3>
          <p>Slot dikira ikut tempoh servis dan jadual setiap staf. Dua orang tekan serentak pun, hanya seorang yang dapat.</p>
        </article>
        <article class="feat">
          <span class="f-ic"><?= icon('send') ?></span>
          <h3>Notifikasi Telegram percuma</h3>
          <p>Setiap tempahan baru terus masuk ke telefon anda dengan nama, servis dan masa. Tiada caj SMS.</p>
        </article>
        <article class="feat">
          <span class="f-ic"><?= icon('users') ?></span>
          <h3>Pelanggan pilih staf</h3>
          <p>Nak barber kegemaran? Boleh. Pilih "sesiapa" pula, sistem beri kepada staf yang paling lapang.</p>
        </article>
        <article class="feat">
          <span class="f-ic"><?= icon('qr') ?></span>
          <h3>Poster QR siap cetak</h3>
          <p>Cetak dan tampal di kaunter. Pelanggan yang sedang tunggu boleh terus tempah untuk bulan depan.</p>
        </article>
        <article class="feat">
          <span class="f-ic"><?= icon('chat') ?></span>
          <h3>Peringatan WhatsApp satu klik</h3>
          <p>Mesej peringatan dah siap ditulis. Tekan sekali, WhatsApp terbuka, tekan hantar. Kurangkan pelanggan yang tak datang.</p>
        </article>
        <article class="feat">
          <span class="f-ic"><?= icon('chart') ?></span>
          <h3>Laporan jualan</h3>
          <p>Nampak berapa tempahan dan anggaran jualan hari ini, minggu ini dan bulan ini, termasuk servis paling laku.</p>
        </article>
        <article class="feat">
          <span class="f-ic"><?= icon('clock') ?></span>
          <h3>Waktu & cuti fleksibel</h3>
          <p>Waktu berbeza setiap hari, hari cuti staf, cuti umum dan notis minimum sebelum tempahan.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- ============ CARA ============ -->
  <section class="section how-sec">
    <div class="wrap">
      <div class="sec-head">
        <span class="kicker">Cara mula</span>
        <h2>Dari daftar ke tempahan pertama dalam 5 minit</h2>
      </div>
      <ol class="how">
        <li><span class="n">1</span><h3>Daftar kedai</h3><p>Nama kedai, pautan dan kata laluan. Servis contoh dan waktu operasi disediakan terus.</p></li>
        <li><span class="n">2</span><h3>Ubah harga & waktu</h3><p>Tukar ikut kedai anda. Tambah staf, muat naik logo, pilih warna.</p></li>
        <li><span class="n">3</span><h3>Kongsi pautan</h3><p>Salin ke bio media sosial atau cetak poster QR. Tempahan mula masuk.</p></li>
      </ol>
    </div>
  </section>

  <!-- ============ HARGA ============ -->
  <section class="section" id="harga">
    <div class="wrap">
      <div class="sec-head">
        <span class="kicker">Harga</span>
        <h2>Mula percuma. Naik taraf bila kedai makin sibuk.</h2>
        <p class="muted">Semua kedai baru dapat <b><?= $trialDays ?> hari pelan Pro</b> secara percuma. Lepas itu kedai terus jalan dengan pelan Percuma, dan tiada caj automatik.</p>
        <div class="toggle" role="group" aria-label="Tempoh bayaran">
          <button type="button" class="on" data-period="1">Bulanan</button>
          <button type="button" data-period="12">Tahunan <span class="save">2 bulan percuma</span></button>
        </div>
      </div>
      <div class="pricing">
        <?php foreach (PLANS as $key => $p): $pop = $key === 'asas'; ?>
        <article class="price-card<?= $pop ? ' pop' : '' ?>">
          <?php if ($pop): ?><span class="pop-tag">Paling sesuai untuk mula</span><?php endif; ?>
          <h3><?= e($p['name']) ?></h3>
          <p class="muted small"><?= e($p['blurb']) ?></p>
          <div class="price">
            <?php if ($p['price'] === 0): ?>
              <span class="amt">RM0</span><span class="per">selamanya</span>
            <?php else: ?>
              <span class="amt" data-m="<?= e(rm($p['price'])) ?>" data-y="<?= e(rm((int)round(plan_price($key, 12) / 12))) ?>"><?= e(rm($p['price'])) ?></span><span class="per">/bulan</span>
              <span class="yearly small muted" data-y="Dibil <?= e(rm(plan_price($key, 12))) ?> setahun"></span>
            <?php endif; ?>
          </div>
          <ul class="plist">
            <li><?= icon('check', 'ic-sm') ?> <?= $p['bookings'] === null ? '<b>Tempahan online tanpa had</b>' : '<b>' . $p['bookings'] . '</b> tempahan online sebulan' ?></li>
            <li><?= icon('check', 'ic-sm') ?> <b><?= $p['staff'] ?></b> staf boleh ditempah</li>
            <li><?= icon('check', 'ic-sm') ?> Pautan kedai + poster QR</li>
            <li><?= icon('check', 'ic-sm') ?> Panel tempahan & laporan</li>
            <li class="<?= $p['telegram'] ? '' : 'no' ?>"><?= icon($p['telegram'] ? 'check' : 'x', 'ic-sm') ?> Notifikasi Telegram</li>
            <li class="<?= $p['branding'] ? '' : 'no' ?>"><?= icon($p['branding'] ? 'check' : 'x', 'ic-sm') ?> Warna jenama & gambar muka depan</li>
            <li class="<?= $p['white_label'] ? '' : 'no' ?>"><?= icon($p['white_label'] ? 'check' : 'x', 'ic-sm') ?> Tanpa label "Dikuasakan oleh <?= e(cfg('app_name')) ?>"</li>
          </ul>
          <a class="btn btn-block <?= $pop ? 'btn-brand' : 'btn-ghost' ?>" href="daftar.php?pelan=<?= e($key) ?>"><?= $p['price'] === 0 ? 'Mula percuma' : 'Cuba ' . $trialDays . ' hari percuma' ?></a>
        </article>
        <?php endforeach; ?>
      </div>
      <p class="center muted small pay-note"><?= icon('lock', 'ic-sm') ?> Bayar dengan FPX online banking (ToyyibPay) atau pindahan bank. Tiada kontrak dan tiada auto-debit.</p>
    </div>
  </section>

  <!-- ============ SOALAN ============ -->
  <section class="section faq-sec" id="soalan">
    <div class="wrap narrow">
      <div class="sec-head">
        <span class="kicker">Soalan lazim</span>
        <h2>Ada soalan?</h2>
      </div>
      <div class="faq">
        <details open><summary>Perlu ada laman web sendiri?</summary><p>Tidak. Anda dapat pautan seperti <b><?= e(preg_replace('#^https?://#', '', url('nama-kedai'))) ?></b>. Letak pautan itu di bio Instagram, TikTok, Facebook, Google Maps atau status WhatsApp.</p></details>
        <details><summary>Pelanggan perlu buat akaun atau muat turun app?</summary><p>Tidak. Pelanggan cuma isi nama dan nombor telefon. Mereka boleh semak atau batal tempahan guna kod tempahan.</p></details>
        <details><summary>Macam mana saya tahu ada tempahan baru?</summary><p>Melalui notifikasi Telegram yang percuma terus ke telefon anda, dan semua tempahan juga dipapar dalam panel. Mesej peringatan WhatsApp kepada pelanggan boleh dihantar dengan satu klik.</p></details>
        <details><summary>Apa jadi lepas percubaan <?= $trialDays ?> hari?</summary><p>Kedai anda terus berjalan dengan pelan Percuma (<?= PLANS['percuma']['bookings'] ?> tempahan online sebulan, <?= PLANS['percuma']['staff'] ?> staf). Tiada caj dikenakan kecuali anda sendiri pilih untuk naik taraf.</p></details>
        <details><summary>Macam mana bayar langganan?</summary><p>Melalui FPX online banking (ToyyibPay) atau pindahan bank dengan muat naik resit. Anda pilih nak bayar 1, 3 atau 12 bulan. Pelan tahunan dapat 2 bulan percuma.</p></details>
        <details><summary>Pelanggan bayar servis macam mana?</summary><p>Di kedai seperti biasa. <?= e(cfg('app_name')) ?> hanya uruskan tempahan, jadi duit servis masuk terus kepada anda, bukan melalui kami.</p></details>
        <details><summary>Ada ganjaran kalau saya ajak kedai lain?</summary><p>Ada. Setiap kedai dapat pautan jemputan sendiri. Bila kedai yang anda jemput membuat bayaran pertama, anda dapat <?= REFERRAL_DAYS ?> hari percuma, dan tiada had bilangan jemputan.</p></details>
        <details><summary>Boleh tukar pelan tengah jalan?</summary><p>Boleh, bila-bila masa. Kalau anda naik taraf, baki hari pelan lama ditukar kepada hari pelan baru ikut nilai, jadi tiada duit yang hangus.</p></details>
      </div>
    </div>
  </section>

  <!-- ============ CTA ============ -->
  <section class="cta-band">
    <div class="wrap cta-in">
      <h2>Kedai anda boleh terima tempahan online hari ini.</h2>
      <a class="btn btn-brand btn-lg" href="daftar.php">Daftar kedai percuma <?= icon('arrow', 'ic-sm') ?></a>
    </div>
  </section>
</main>
<?php
$footScripts = <<<'JS'
<script>
// Togol harga bulanan / tahunan
document.querySelectorAll('.toggle button').forEach(function (b) {
  b.addEventListener('click', function () {
    var yearly = b.dataset.period === '12';
    document.querySelectorAll('.toggle button').forEach(function (x) { x.classList.toggle('on', x === b); });
    document.querySelectorAll('.price .amt[data-m]').forEach(function (el) { el.textContent = yearly ? el.dataset.y : el.dataset.m; });
    document.querySelectorAll('.price .yearly').forEach(function (el) { el.textContent = yearly ? el.dataset.y : ''; });
  });
});
</script>
JS;
include ROOT . '/includes/site_foot.php';
