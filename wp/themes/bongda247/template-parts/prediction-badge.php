<?php
defined('ABSPATH') || exit;
$bd_s = bd_prediction_stats();
if ($bd_s['total'] === 0) return; // chưa có số liệu → ẩn
?>
<a href="<?php echo esc_url(home_url('/thanh-tich-du-doan/')); ?>" class="inline-flex items-center gap-3 rounded-2xl border border-card bg-card px-5 py-3 hover:border-brand transition-colors">
  <span class="font-hemi text-3xl text-brand"><?php echo esc_html($bd_s['outcome_pct']); ?>%</span>
  <span class="text-sm text-secondary leading-tight">AI dự đoán đúng<br>qua <?php echo esc_html($bd_s['total']); ?> trận →</span>
</a>
