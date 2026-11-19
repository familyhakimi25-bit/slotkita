<?php
declare(strict_types=1);

/**
 * Pelan langganan. Harga dalam sen. null = tanpa had.
 * Tukar harga di sini sahaja - semua halaman (laman utama, langganan, bil) baca dari sini.
 */
const PLANS = [
    'percuma' => [
        'name' => 'Percuma', 'price' => 0,
        'staff' => 1, 'bookings' => 30,
        'telegram' => false, 'branding' => false, 'white_label' => false,
        'blurb' => 'Untuk cuba dan kedai seorang yang baru bermula.',
    ],
    'asas' => [
        'name' => 'Asas', 'price' => 3900,
        'staff' => 3, 'bookings' => 300,
        'telegram' => true, 'branding' => true, 'white_label' => false,
        'blurb' => 'Kedai kecil dengan 2-3 orang staf.',
    ],
    'pro' => [
        'name' => 'Pro', 'price' => 7900,
        'staff' => 15, 'bookings' => null,
        'telegram' => true, 'branding' => true, 'white_label' => true,
        'blurb' => 'Kedai sibuk yang mahu tempahan tanpa had.',
    ],
];

/** Tempoh bayaran => bilangan bulan yang dicaj (12 bulan = bayar 10) */
const PERIODS = [1 => 1, 3 => 3, 12 => 10];

function plan_price(string $plan, int $months): int
{
    return PLANS[$plan]['price'] * (PERIODS[$months] ?? $months);
}

/** Pelan yang BERKUAT KUASA hari ini (bayaran > percubaan > percuma). */
function shop_plan(array $shop): string
{
    $today = date('Y-m-d');
    if ($shop['plan'] !== 'percuma' && isset(PLANS[$shop['plan']]) && $shop['paid_until'] && $shop['paid_until'] >= $today) {
        return $shop['plan'];
    }
    if ($shop['trial_until'] && $shop['trial_until'] >= $today) {
        return 'pro';
    }
    return 'percuma';
}

function on_trial(array $shop): bool
{
    $today = date('Y-m-d');
    $paid = $shop['plan'] !== 'percuma' && $shop['paid_until'] && $shop['paid_until'] >= $today;
    return !$paid && $shop['trial_until'] && $shop['trial_until'] >= $today;
}

function plan_has(array $shop, string $feature): bool
{
    return (bool)PLANS[shop_plan($shop)][$feature];
}

function days_left(?string $ymd): int
{
    if (!$ymd) {
        return -1;
    }
    return (int)floor((strtotime($ymd) - strtotime(date('Y-m-d'))) / 86400);
}

/** Tempahan online bulan ini (yang tidak dibatalkan) - dikira untuk had pelan. */
function month_online_bookings(int $shopId): int
{
    return (int)val(
        "SELECT COUNT(*) FROM bookings WHERE shop_id = ? AND source = 'online' AND status <> 'batal' AND created_at >= ?",
        [$shopId, date('Y-m-01 00:00:00')]
    );
}

function booking_quota_left(array $shop): ?int
{
    $limit = PLANS[shop_plan($shop)]['bookings'];
    if ($limit === null) {
        return null;
    }
    return max(0, $limit - month_online_bookings((int)$shop['id']));
}

/**
 * Sahkan bayaran & lanjutkan langganan. Idempotent - selamat dipanggil banyak kali
 * (callback + return URL boleh sampai serentak).
 */
function apply_payment(int $paymentId, string $refNo = ''): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $p = row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        // 'failed' dibenarkan: pembayar boleh cuba semula bil yang sama di ToyyibPay dan berjaya
        if (!$p || !in_array($p['status'], ['pending', 'failed'], true)) {
            $pdo->commit();
            return false;
        }
        $shop = row('SELECT * FROM shops WHERE id = ? FOR UPDATE', [$p['shop_id']]);
        $today = date('Y-m-d');
        $activePaid = $shop['plan'] !== 'percuma' && $shop['paid_until'] && $shop['paid_until'] >= $today;

        $bonus = 0;
        if ($activePaid && $shop['plan'] === $p['plan']) {
            // Lanjut dari hujung tempoh sekarang
            $from = date('Y-m-d', strtotime($shop['paid_until'] . ' +1 day'));
        } else {
            $from = $today;
            if ($activePaid) {
                // Tukar pelan: baki hari pelan lama ditukar kepada hari pelan baru ikut nilai
                $remaining = days_left($shop['paid_until']);
                $bonus = (int)floor($remaining * PLANS[$shop['plan']]['price'] / max(1, PLANS[$p['plan']]['price']));
            } elseif ($shop['trial_until'] && $shop['trial_until'] >= $today) {
                // Bayar semasa percubaan: baki hari percubaan tidak hangus
                $bonus = days_left($shop['trial_until']);
            }
        }
        $to = date('Y-m-d', strtotime($from . ' +' . (int)$p['months'] . ' months -1 day +' . $bonus . ' days'));

        q('UPDATE shops SET plan = ?, paid_until = ? WHERE id = ?', [$p['plan'], $to, $shop['id']]);
        q(
            "UPDATE payments SET status = 'paid', paid_at = NOW(), period_from = ?, period_to = ?, ref_no = COALESCE(NULLIF(?, ''), ref_no) WHERE id = ?",
            [$from, $to, $refNo, $paymentId]
        );
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    require_once ROOT . '/lib/telegram.php';
    tg_admin(sprintf("💰 Bayaran masuk %s\n%s — pelan %s, %d bulan\nAktif hingga %s",
        rm((int)$p['amount_sen']), $shop['name'], PLANS[$p['plan']]['name'], $p['months'], tarikh($to, false)));
    reward_referrer((int)$shop['id']);
    return true;
}

const REFERRAL_DAYS = 30;

/**
 * Bayaran pertama kedai yang dijemput -> penjemput dapat 30 hari percuma (sekali sahaja).
 * Berbayar: lanjut tempoh. Percubaan: lanjut percubaan. Percuma: dapat Asas 30 hari.
 */
function reward_referrer(int $shopId): void
{
    $s = row('SELECT id, name, referred_by FROM shops WHERE id = ?', [$shopId]);
    if (!$s || !$s['referred_by'] || (int)$s['referred_by'] === $shopId) {
        return;
    }
    // Kunci ganjaran: hanya satu proses boleh tukar 0 -> 1
    if (q('UPDATE shops SET ref_rewarded = 1 WHERE id = ? AND ref_rewarded = 0', [$shopId])->rowCount() !== 1) {
        return;
    }
    $r = row('SELECT * FROM shops WHERE id = ?', [$s['referred_by']]);
    if (!$r) {
        return;
    }
    $today = date('Y-m-d');
    $d = REFERRAL_DAYS;
    if ($r['plan'] !== 'percuma' && $r['paid_until'] && $r['paid_until'] >= $today) {
        q("UPDATE shops SET paid_until = DATE_ADD(paid_until, INTERVAL $d DAY) WHERE id = ?", [$r['id']]);
    } elseif ($r['trial_until'] && $r['trial_until'] >= $today) {
        q("UPDATE shops SET trial_until = DATE_ADD(trial_until, INTERVAL $d DAY) WHERE id = ?", [$r['id']]);
    } else {
        q("UPDATE shops SET plan = 'asas', paid_until = ? WHERE id = ?", [date('Y-m-d', strtotime('+' . ($d - 1) . ' days')), $r['id']]);
    }
    if ($r['tg_chat_id']) {
        require_once ROOT . '/lib/telegram.php';
        tg_send($r['tg_chat_id'], "🎁 {$s['name']} yang anda jemput telah melanggan. Anda dapat $d hari percuma. Terima kasih!");
    }
}
