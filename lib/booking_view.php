<?php
declare(strict_types=1);

const STATUS_BADGE = [
    'menunggu'    => 'badge-warn',
    'disahkan'    => 'badge-info',
    'selesai'     => 'badge-ok',
    'tidak_hadir' => 'badge-bad',
    'batal'       => '',
];

/** Tempahan kedai dengan nama servis & staf (subquery - selamat untuk ONLY_FULL_GROUP_BY) */
function shop_bookings(int $shopId, string $where = '1=1', array $params = [], string $order = 'b.start_at', int $limit = 300): array
{
    return rows(
        "SELECT b.*, st.name AS staff_name,
                (SELECT GROUP_CONCAT(bs.name ORDER BY bs.name SEPARATOR ', ') FROM booking_services bs WHERE bs.booking_id = b.id) AS services
         FROM bookings b LEFT JOIN staff st ON st.id = b.staff_id
         WHERE b.shop_id = ? AND ($where) ORDER BY $order LIMIT $limit",
        array_merge([$shopId], $params)
    );
}

function reminder_text(array $shop, array $b): string
{
    $t = strtotime($b['start_at']);
    return "Salam {$b['customer_name']}, ini peringatan tempahan anda di {$shop['name']}:\n"
        . tarikh(date('Y-m-d', $t)) . ', ' . masa(date('H:i:s', $t)) . "\n"
        . ($b['services'] ?? '') . "\nKod: {$b['code']}\n\nJumpa nanti! Jika perlu batal: " . url('semak.php?k=' . $shop['slug'] . '&c=' . $b['code']);
}

/** Satu baris tempahan dalam senarai panel */
function booking_row(array $shop, array $b, bool $showDate = false, string $back = ''): string
{
    $t = strtotime($b['start_at']);
    $past = strtotime($b['end_at']) < time();
    $holding = in_array($b['status'], HOLDING, true);
    ob_start(); ?>
    <div class="bk<?= $past && !$holding ? ' past' : '' ?>">
      <div class="bk-time">
        <b><?= e(date('g:i', $t)) ?></b>
        <small><?= e(date('A', $t) === 'AM' ? 'PG' : (date('G', $t) < 19 ? 'PTG' : 'MLM')) ?><?= $showDate ? '<br>' . e(date('j', $t) . ' ' . BULAN[(int)date('n', $t) - 1]) : '' ?></small>
      </div>
      <div class="bk-main">
        <div class="who">
          <?= e($b['customer_name']) ?>
          <span class="badge <?= STATUS_BADGE[$b['status']] ?? '' ?>"><?= e(STATUS[$b['status']] ?? $b['status']) ?></span>
          <?php if ($b['source'] === 'walkin'): ?><span class="badge">Walk-in</span><?php endif; ?>
        </div>
        <div class="what"><?= e($b['services'] ?? '') ?> · <?= e(rm((int)$b['total_sen'])) ?><?= $b['staff_name'] ? ' · ' . e($b['staff_name']) : '' ?> · <span class="nowrap">#<?= e($b['code']) ?></span></div>
        <?php if ($b['note']): ?><div class="note"><?= e($b['note']) ?></div><?php endif; ?>
      </div>
      <div class="bk-act">
        <?php if ($b['customer_phone'] === ''): ?>
        <?php elseif ($holding && !$past): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(wa_link($b['customer_phone'], reminder_text($shop, $b))) ?>" target="_blank" rel="noopener" title="Hantar peringatan WhatsApp"><?= icon('chat', 'ic-sm') ?> Ingatkan</a>
        <?php else: ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(wa_link($b['customer_phone'])) ?>" target="_blank" rel="noopener" title="<?= e(show_phone($b['customer_phone'])) ?>"><?= icon('chat', 'ic-sm') ?></a>
        <?php endif; ?>
        <?php if ($b['status'] === 'menunggu'): ?>
          <form method="post" action="<?= e(url('app/tempahan.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="btn btn-ok btn-sm" name="to" value="disahkan"><?= icon('check', 'ic-sm') ?> Sahkan</button></form>
        <?php elseif ($b['status'] === 'disahkan' && $t <= time() + 3600): ?>
          <form method="post" action="<?= e(url('app/tempahan.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="btn btn-sm" name="to" value="selesai"><?= icon('check', 'ic-sm') ?> Selesai</button></form>
        <?php endif; ?>
        <details class="dropdown">
          <summary class="btn btn-ghost btn-sm btn-icon" aria-label="Lagi tindakan">⋯</summary>
          <div class="menu">
            <?php if ($b['customer_phone'] !== ''): ?><a href="tel:+<?= e($b['customer_phone']) ?>"><?= icon('phone', 'ic-sm') ?> <?= e(show_phone($b['customer_phone'])) ?></a><?php endif; ?>
            <?php foreach (['disahkan' => 'Tanda disahkan', 'selesai' => 'Tanda selesai', 'tidak_hadir' => 'Tanda tidak hadir', 'menunggu' => 'Kembali ke menunggu'] as $to => $label):
                if ($to === $b['status']) continue; ?>
              <form method="post" action="<?= e(url('app/tempahan.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                <button name="to" value="<?= e($to) ?>"><?= e($label) ?></button></form>
            <?php endforeach; ?>
            <?php if ($b['status'] !== 'batal'): ?>
              <form method="post" action="<?= e(url('app/tempahan.php')) ?>" data-confirm="Batalkan tempahan <?= e($b['customer_name']) ?>? Slot akan dibuka semula."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="danger" name="to" value="batal"><?= icon('x', 'ic-sm') ?> Batalkan</button></form>
            <?php endif; ?>
          </div>
        </details>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
}
