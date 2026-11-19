<?php
declare(strict_types=1);

/** Langkah paksi yang "bulat": 1, 2, 5, 10, 20, 50 ... */
function nice_step(float $max, int $ticks = 4): float
{
    if ($max <= 0) {
        return 1;
    }
    $raw = $max / $ticks;
    $mag = 10 ** floor(log10($raw));
    foreach ([1, 2, 5, 10] as $m) {
        if ($raw <= $m * $mag) {
            return $m * $mag;
        }
    }
    return 10 * $mag;
}

/**
 * Carta lajur satu siri (SVG) + tooltip hover + jadual.
 * $points = [['label' => 'Isn 21', 'tip' => 'Isnin, 21 Sep', 'value' => 7], ...]
 * $fmt    = fn(value) => teks paparan (cth rm())
 * Warna #d9480f disahkan dengan validate_palette.js (kontras >= 3:1 atas putih).
 */
function column_chart(array $points, callable $fmt, string $caption, ?callable $axisFmt = null): string
{
    $axisFmt = $axisFmt ?? $fmt;
    $W = 640; $H = 220; $L = 44; $R = 8; $T = 22; $B = 26;
    $n = max(1, count($points));
    $max = max(array_merge([0], array_column($points, 'value')));
    $step = nice_step((float)$max);
    $top = max($step, ceil($max / $step) * $step);
    $plotW = $W - $L - $R;
    $plotH = $H - $T - $B;
    $band = $plotW / $n;
    $bw = min(24, $band * 0.62);
    $y = fn($v) => $T + $plotH - ($v / $top) * $plotH;

    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($caption) . '">';
    for ($v = 0; $v <= $top + 1e-9; $v += $step) {
        $yy = round($y($v), 1);
        $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" stroke="' . ($v == 0 ? '#d9d0c5' : '#f0ebe4') . '" stroke-width="1"/>';
        $svg .= '<text x="' . ($L - 8) . '" y="' . ($yy + 4) . '" text-anchor="end" font-size="11" fill="#6b6259" style="font-variant-numeric:tabular-nums">' . e($axisFmt($v)) . '</text>';
    }
    $every = (int)ceil($n / 8); // paling banyak ~8 label paksi-x supaya tidak bertindih
    foreach ($points as $i => $p) {
        $cx = $L + $band * $i + $band / 2;
        $x0 = round($cx - $bw / 2, 1);
        $v = (float)$p['value'];
        if ($v > 0) {
            $yt = $y($v);
            $h = $T + $plotH - $yt;
            $r = min(4, $h, $bw / 2);
            // bucu atas bulat 4px, tapak rata
            $svg .= sprintf(
                '<path d="M%.1f %.1f V%.1f Q%.1f %.1f %.1f %.1f H%.1f Q%.1f %.1f %.1f %.1f V%.1f Z" fill="#d9480f"/>',
                $x0, $T + $plotH, $yt + $r, $x0, $yt, $x0 + $r, $yt, $x0 + $bw - $r, $x0 + $bw, $yt, $x0 + $bw, $yt + $r, $T + $plotH
            );
        }
        $last = $i === $n - 1;
        if ($i % $every === ($n - 1) % $every || $last) {
            $svg .= '<text x="' . round($cx, 1) . '" y="' . ($H - 8) . '" text-anchor="middle" font-size="11" fill="' . ($last ? '#16130f' : '#6b6259') . '" font-weight="' . ($last ? 700 : 500) . '">' . e($p['label']) . '</text>';
        }
        if ($last && $v > 0) {
            // label terus pada lajur semasa sahaja
            $svg .= '<text x="' . round($cx, 1) . '" y="' . round($y($v) - 7, 1) . '" text-anchor="middle" font-size="12" font-weight="800" fill="#16130f">' . e($fmt($v)) . '</text>';
        }
        // sasaran hover = seluruh jalur, lebih besar dari lajur
        $svg .= '<rect class="hit" x="' . round($L + $band * $i, 1) . '" y="' . $T . '" width="' . round($band, 1) . '" height="' . $plotH . '" fill="transparent" data-tip="' . e($p['tip'] . ': ' . $fmt($v)) . '"><title>' . e($p['tip'] . ': ' . $fmt($v)) . '</title></rect>';
    }
    $svg .= '</svg>';

    $rows = '';
    foreach ($points as $p) {
        $rows .= '<tr><td>' . e($p['tip']) . '</td><td class="num">' . e($fmt((float)$p['value'])) . '</td></tr>';
    }
    return '<figure class="chart-box" style="margin:0">' . $svg . '<div class="chart-tip" hidden></div>'
        . '<details class="small" style="margin-top:.5rem"><summary class="muted" style="cursor:pointer">Lihat sebagai jadual</summary>'
        . '<table class="table" style="margin-top:.5rem"><thead><tr><th>' . e($caption) . '</th><th class="num">Nilai</th></tr></thead><tbody>' . $rows . '</tbody></table></details></figure>';
}

/** Skrip tooltip - sertakan sekali di halaman yang ada carta */
function chart_script(): string
{
    return <<<'HTML'
<style>
.chart-box .hit { cursor: default; }
.chart-box .hit:hover { fill: rgba(22,19,15,.04); }
.chart-tip { position: absolute; pointer-events: none; transform: translate(-50%, -100%); background: #16130f; color: #fff; font-size: .8rem; font-weight: 700; padding: .35rem .6rem; border-radius: 8px; white-space: nowrap; z-index: 5; }
</style>
<script>
document.querySelectorAll('.chart-box').forEach(function (box) {
  var tip = box.querySelector('.chart-tip');
  box.querySelectorAll('.hit').forEach(function (h) {
    h.addEventListener('mouseenter', function () {
      var r = h.getBoundingClientRect(), b = box.getBoundingClientRect();
      tip.textContent = h.dataset.tip;
      tip.style.left = (r.left - b.left + r.width / 2) + 'px';
      tip.style.top = (r.top - b.top + 6) + 'px';
      tip.hidden = false;
    });
    h.addEventListener('mouseleave', function () { tip.hidden = true; });
  });
});
</script>
HTML;
}
