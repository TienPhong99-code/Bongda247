<?php
// Lịch thi đấu & kết quả — nhận data qua set_query_var('bd_fd_matches').
defined('ABSPATH') || exit;
$matches = get_query_var('bd_fd_matches');
if (empty($matches)) return;

// Nhóm theo ngày (giờ VN qua wp_date).
$bd_by_date = [];
foreach ($matches as $m) {
    $ts = strtotime($m['utcDate']);
    if (!$ts) continue;
    $day = wp_date('D, d/m', $ts);
    $m['ts'] = $ts;
    $bd_by_date[$day][] = $m;
}
?>
<div class="space-y-6">
  <?php foreach ($bd_by_date as $day => $list) : ?>
    <div>
      <h4 class="text-xs uppercase tracking-wide text-secondary mb-2"><?php echo esc_html($day); ?></h4>
      <ul class="rounded-lg border border-card">
        <?php foreach ($list as $m) :
            $finished = ($m['status'] === 'FINISHED');
            $mid = $finished ? ($m['sh'] . ' - ' . $m['sa']) : wp_date('H:i', $m['ts']); ?>
          <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm border-t border-card first:border-t-0">
            <span class="flex-1 text-right truncate"><?php echo esc_html($m['home']); ?></span>
            <span class="px-3 font-bold whitespace-nowrap <?php echo $finished ? 'text-brand' : 'text-secondary'; ?>"><?php echo esc_html($mid); ?></span>
            <span class="flex-1 truncate"><?php echo esc_html($m['away']); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</div>
