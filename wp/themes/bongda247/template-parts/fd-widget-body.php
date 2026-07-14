<?php
defined('ABSPATH') || exit;

$bd_slug = get_query_var('bd_fd_widget_slug') ?: 'ngoai-hang-anh';
$bd_code = bd_fd_code($bd_slug);
$bd_rows = $bd_code ? array_slice(bd_fd_standings($bd_code), 0, 8) : [];

$bd_up = [];   // trận sắp tới
$bd_res = [];  // kết quả
if ($bd_code) {
    foreach (bd_fd_fixtures($bd_code) as $m) {
        if (($m['status'] ?? '') === 'FINISHED') {
            $bd_res[] = $m;
        } elseif (in_array($m['status'] ?? '', ['SCHEDULED', 'TIMED'], true)) {
            $bd_up[] = $m;
        }
    }
    $bd_res = array_slice(array_reverse($bd_res), 0, 5); // mới nhất trước
    $bd_up  = array_slice($bd_up, 0, 5);
}

// Render 1 dòng trận (dùng chung cho Lịch/KQ).
$bd_row = function ($m, $finished) {
    $ts  = strtotime($m['utcDate'] ?? '');
    $mid = $finished ? (($m['sh'] ?? '') . ' - ' . ($m['sa'] ?? '')) : ($ts ? wp_date('H:i', $ts) : '');
    ob_start(); ?>
    <li class="flex items-center justify-between gap-2 py-1.5 text-xs border-t border-card first:border-t-0">
      <span class="flex-1 text-right truncate"><?php echo esc_html($m['home'] ?? ''); ?></span>
      <span class="px-2 font-bold whitespace-nowrap <?php echo $finished ? 'text-brand' : 'text-secondary'; ?>"><?php echo esc_html($mid); ?></span>
      <span class="flex-1 truncate"><?php echo esc_html($m['away'] ?? ''); ?></span>
    </li>
    <?php return ob_get_clean();
};
?>
<div class="flex gap-1 mb-3">
  <button type="button" data-fd-tab="bxh"  class="px-3 py-1.5 text-xs rounded-full border border-card bg-brand text-white" aria-selected="true">BXH</button>
  <button type="button" data-fd-tab="lich" class="px-3 py-1.5 text-xs rounded-full border border-card text-secondary hover:text-brand" aria-selected="false">Lịch</button>
  <button type="button" data-fd-tab="kq"   class="px-3 py-1.5 text-xs rounded-full border border-card text-secondary hover:text-brand" aria-selected="false">Kết quả</button>
</div>

<div data-fd-panel="bxh">
  <?php if ($bd_rows) : ?>
    <table class="w-full text-xs">
      <tbody>
        <?php foreach ($bd_rows as $r) : ?>
          <tr class="border-t border-card first:border-t-0">
            <td class="py-1.5 pr-2 text-secondary"><?php echo esc_html($r['position']); ?></td>
            <td class="py-1.5">
              <span class="flex items-center gap-1.5">
                <?php if (!empty($r['crest'])) : ?><img src="<?php echo esc_url($r['crest']); ?>" alt="" class="w-4 h-4 object-contain" loading="lazy"><?php endif; ?>
                <span class="truncate"><?php echo esc_html($r['name']); ?></span>
              </span>
            </td>
            <td class="py-1.5 text-right font-bold text-brand"><?php echo esc_html($r['points']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <a href="<?php echo esc_url(add_query_arg('league', $bd_slug, home_url('/bang-xep-hang/'))); ?>" class="block mt-3 text-xs text-secondary hover:text-brand">Xem đầy đủ →</a>
  <?php else : ?>
    <p class="text-xs text-secondary">Chưa có dữ liệu bảng xếp hạng.</p>
  <?php endif; ?>
</div>

<div data-fd-panel="lich" hidden>
  <?php if ($bd_up) : ?>
    <ul><?php foreach ($bd_up as $m) echo $bd_row($m, false); ?></ul>
    <a href="<?php echo esc_url(add_query_arg('league', $bd_slug, home_url('/lich-thi-dau/'))); ?>" class="block mt-3 text-xs text-secondary hover:text-brand">Xem đầy đủ →</a>
  <?php else : ?>
    <p class="text-xs text-secondary">Chưa có lịch thi đấu.</p>
  <?php endif; ?>
</div>

<div data-fd-panel="kq" hidden>
  <?php if ($bd_res) : ?>
    <ul><?php foreach ($bd_res as $m) echo $bd_row($m, true); ?></ul>
    <a href="<?php echo esc_url(add_query_arg('league', $bd_slug, home_url('/lich-thi-dau/'))); ?>" class="block mt-3 text-xs text-secondary hover:text-brand">Xem đầy đủ →</a>
  <?php else : ?>
    <p class="text-xs text-secondary">Chưa có kết quả.</p>
  <?php endif; ?>
</div>
