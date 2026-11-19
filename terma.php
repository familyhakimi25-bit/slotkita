<?php
require __DIR__ . '/bootstrap.php';
$title = 'Terma & privasi — ' . cfg('app_name');
$app = e(cfg('app_name'));
include ROOT . '/includes/site_head.php';
// NOTA UNTUK PEMILIK PLATFORM: ini draf asas. Semak dan sesuaikan (nama syarikat/SSM, alamat) sebelum lancar.
?>
<main class="wrap narrow prose">
  <h1>Terma perkhidmatan</h1>
  <p class="muted">Kemas kini terakhir: <?= e(tarikh(date('Y-m-d', filemtime(__FILE__)), false)) ?></p>

  <h2>1. Perkhidmatan</h2>
  <p><?= $app ?> menyediakan sistem tempahan online untuk perniagaan servis ("Kedai"). Kedai menggunakan <?= $app ?> untuk menerima tempahan daripada pelanggan mereka ("Pelanggan"). <?= $app ?> tidak memproses bayaran servis antara Kedai dan Pelanggan.</p>

  <h2>2. Akaun kedai</h2>
  <ul>
    <li>Anda bertanggungjawab ke atas maklumat kedai, harga dan waktu operasi yang dipaparkan.</li>
    <li>Jaga kerahsiaan kata laluan anda. Semua tindakan melalui akaun anda dianggap dibuat oleh anda.</li>
    <li>Dilarang menggunakan perkhidmatan untuk aktiviti yang menyalahi undang-undang Malaysia, penipuan atau spam.</li>
  </ul>

  <h2>3. Langganan & bayaran</h2>
  <ul>
    <li>Kedai baru mendapat percubaan percuma. Selepas tamat, kedai kekal di pelan Percuma kecuali anda membayar untuk naik taraf.</li>
    <li>Bayaran dibuat terlebih dahulu untuk tempoh yang dipilih (1, 3 atau 12 bulan). Tiada auto-debit.</li>
    <li>Bayaran tidak dikembalikan untuk tempoh yang telah bermula, kecuali jika perkhidmatan tidak dapat digunakan disebabkan kesilapan kami.</li>
    <li>Harga boleh berubah dengan notis sekurang-kurangnya 30 hari. Tempoh yang telah dibayar tidak terjejas.</li>
  </ul>

  <h2>4. Penggantungan</h2>
  <p>Kami boleh menggantung akaun yang melanggar terma ini atau disalahgunakan, dengan notis jika munasabah.</p>

  <h2>5. Had tanggungjawab</h2>
  <p>Kami berusaha memastikan sistem sentiasa tersedia, tetapi tidak menjamin ia bebas gangguan. <?= $app ?> tidak bertanggungjawab atas kerugian tidak langsung, termasuk tempahan terlepas atau pelanggan yang tidak hadir.</p>

  <h1 id="privasi" style="margin-top:3rem">Dasar privasi</h1>
  <p>Dasar ini menerangkan bagaimana data peribadi dikendalikan selaras dengan Akta Perlindungan Data Peribadi 2010 (PDPA).</p>

  <h2>Data yang dikumpul</h2>
  <ul>
    <li><b>Pemilik kedai:</b> nama, emel, nombor telefon, maklumat kedai dan rekod bayaran langganan.</li>
    <li><b>Pelanggan yang membuat tempahan:</b> nama, nombor telefon, nota tempahan dan alamat IP (untuk mengelak spam).</li>
  </ul>

  <h2>Tujuan</h2>
  <p>Data digunakan hanya untuk menguruskan tempahan, menghubungi pelanggan berkenaan tempahan, memproses bayaran langganan dan menghalang penyalahgunaan. Kami tidak menjual data kepada pihak ketiga.</p>

  <h2>Siapa boleh lihat</h2>
  <p>Butiran tempahan Pelanggan hanya boleh dilihat oleh Kedai yang ditempah dan pentadbir <?= $app ?> untuk tujuan sokongan. Bayaran FPX diproses oleh ToyyibPay mengikut dasar privasi mereka.</p>

  <h2>Hak anda</h2>
  <p>Anda boleh meminta untuk melihat, membetulkan atau memadam data anda dengan menghubungi <a class="link" href="mailto:<?= e(cfg('support_email')) ?>"><?= e(cfg('support_email')) ?></a>. Pelanggan juga boleh menghubungi Kedai berkenaan secara terus.</p>

  <h2>Kuki</h2>
  <p>Kami hanya menggunakan kuki sesi yang diperlukan untuk log masuk dan keselamatan borang. Tiada kuki pengiklanan.</p>
</main>
<?php include ROOT . '/includes/site_foot.php';
