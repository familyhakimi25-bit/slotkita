<?php
declare(strict_types=1);

/*
 * Lapisan gerbang bayaran. Dua pemacu:
 *  - toyyibpay : sebenar (akaun individu guna IC, FPX)
 *  - mock      : tiruan untuk ujian di komputer sendiri - JANGAN guna di hosting
 * Tukar di config.php -> 'payment_driver'.
 */

function pay_driver(): string
{
    return cfg('payment_driver') === 'toyyibpay' ? 'toyyibpay' : 'mock';
}

function tp_base(): string
{
    return cfg('toyyibpay.sandbox') ? 'https://dev.toyyibpay.com' : 'https://toyyibpay.com';
}

function tp_post(string $endpoint, array $params): array
{
    $ch = curl_init(tp_base() . '/index.php/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $data = is_string($res) ? json_decode($res, true) : null;
    if (!is_array($data)) {
        error_log("ToyyibPay $endpoint: " . ($err ?: (string)$res));
        throw new RuntimeException('Gerbang bayaran tidak dapat dihubungi. Cuba sebentar lagi.');
    }
    return $data;
}

/** ToyyibPay: nama/penerangan bil hanya terima huruf, nombor, ruang dan _ */
function tp_clean(string $s, int $max): string
{
    return substr(trim((string)preg_replace(['/[^A-Za-z0-9 _]+/', '/ {2,}/'], ' ', $s)), 0, $max);
}

/**
 * Cipta bil. Pulangkan ['bill_code' => ..., 'url' => ...].
 */
function pay_create_bill(array $payment, array $shop, array $user): array
{
    $return = url('api/bayar-kembali.php');
    if (pay_driver() === 'mock') {
        $code = 'MK' . strtolower(random_code(8));
        file_put_contents(ROOT . "/storage/mock_bills/$code.json", json_encode([
            'order_id' => $payment['order_id'], 'amount_sen' => (int)$payment['amount_sen'],
            'status' => 'pending', 'ref' => '', 'name' => $shop['name'],
        ]));
        return ['bill_code' => $code, 'url' => url('api/mock-bayar.php?bill=' . $code)];
    }

    $params = [
        'userSecretKey'           => cfg('toyyibpay.secret_key'),
        'categoryCode'            => cfg('toyyibpay.category_code'),
        'billName'                => tp_clean(cfg('app_name') . ' ' . PLANS[$payment['plan']]['name'] . ' ' . $payment['months'] . 'bln', 30),
        'billDescription'         => tp_clean('Langganan ' . cfg('app_name') . ' untuk ' . $shop['name'], 100),
        'billPriceSetting'        => 1,
        'billPayorInfo'           => 1,
        'billAmount'              => (int)$payment['amount_sen'],
        'billReturnUrl'           => $return,
        'billCallbackUrl'         => url('api/toyyibpay-callback.php'),
        'billExternalReferenceNo' => $payment['order_id'],
        'billTo'                  => substr($user['name'], 0, 60),
        'billEmail'               => $user['email'],
        'billPhone'               => $user['phone'] ?: ($shop['phone'] ?: '0100000000'),
        'billPaymentChannel'      => 0,
        'billExpiryDays'          => 3,
    ];
    if (cfg('toyyibpay.charge_customer')) {
        $params['billChargeToCustomer'] = 0;
    }
    $data = tp_post('createBill', $params);
    $code = $data[0]['BillCode'] ?? '';
    if ($code === '') {
        error_log('ToyyibPay createBill: ' . json_encode($data));
        throw new RuntimeException('Bil tidak dapat dicipta. Semak tetapan ToyyibPay.');
    }
    return ['bill_code' => $code, 'url' => tp_base() . '/' . $code];
}

/**
 * Tanya status sebenar bil TERUS kepada gerbang (jangan percaya parameter URL).
 * Pulangkan ['status' => paid|pending|failed, 'amount_sen' => int, 'ref' => string, 'order_id' => string]
 */
function pay_bill_status(string $billCode): array
{
    if (pay_driver() === 'mock') {
        $f = ROOT . '/storage/mock_bills/' . basename($billCode) . '.json';
        if (!preg_match('/^MK[a-z0-9]{8}$/', $billCode) || !is_file($f)) {
            return ['status' => 'failed', 'amount_sen' => 0, 'ref' => '', 'order_id' => ''];
        }
        $m = json_decode((string)file_get_contents($f), true);
        return ['status' => $m['status'], 'amount_sen' => (int)$m['amount_sen'], 'ref' => $m['ref'], 'order_id' => $m['order_id']];
    }

    $list = tp_post('getBillTransactions', ['billCode' => $billCode]);
    $out = ['status' => 'pending', 'amount_sen' => 0, 'ref' => '', 'order_id' => ''];
    foreach ($list as $tx) {
        if (!is_array($tx)) {
            continue;
        }
        $out['order_id'] = (string)($tx['billExternalReferenceNo'] ?? '');
        if ((string)($tx['billpaymentStatus'] ?? '') === '1') {
            return [
                'status'     => 'paid',
                'amount_sen' => (int)round(((float)($tx['billpaymentAmount'] ?? 0)) * 100),
                'ref'        => (string)($tx['billpaymentInvoiceNo'] ?? ''),
                'order_id'   => $out['order_id'],
            ];
        }
        if ((string)($tx['billpaymentStatus'] ?? '') === '3') {
            $out['status'] = 'failed';
        }
    }
    return $out;
}

/** Semak hash callback ToyyibPay: md5(secret + status + order_id + refno + "ok") */
function tp_callback_valid(array $post): bool
{
    $expected = md5(cfg('toyyibpay.secret_key') . ($post['status'] ?? '') . ($post['order_id'] ?? '') . ($post['refno'] ?? '') . 'ok');
    return isset($post['hash']) && is_string($post['hash']) && hash_equals($expected, $post['hash']);
}

/**
 * Proses satu bil selepas callback/return. Sentiasa sahkan dengan gerbang.
 * Pulangkan status akhir bayaran: paid | pending | failed | unknown
 */
function process_bill(string $billCode): string
{
    $p = row('SELECT * FROM payments WHERE bill_code = ?', [$billCode]);
    if (!$p) {
        return 'unknown';
    }
    if ($p['status'] === 'paid') {
        return 'paid';
    }
    $g = pay_bill_status($billCode);
    if ($g['status'] === 'paid') {
        if ($g['amount_sen'] !== (int)$p['amount_sen'] || ($g['order_id'] !== '' && $g['order_id'] !== $p['order_id'])) {
            error_log("Bayaran tidak sepadan bil $billCode: " . json_encode($g));
            q("UPDATE payments SET note = 'Jumlah tidak sepadan - semak manual' WHERE id = ?", [$p['id']]);
            return 'pending';
        }
        apply_payment((int)$p['id'], $g['ref']);
        return 'paid';
    }
    if ($g['status'] === 'failed' && $p['status'] === 'pending') {
        q("UPDATE payments SET status = 'failed' WHERE id = ? AND status = 'pending'", [$p['id']]);
        return 'failed';
    }
    return $p['status'] === 'failed' ? 'failed' : 'pending';
}
