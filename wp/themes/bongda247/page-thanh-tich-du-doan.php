<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Thành tích dự đoán</h1>

  <?php $bd_s = bd_prediction_stats(20); ?>
  <?php if ($bd_s['total'] === 0) : ?>
    <p class="text-secondary">Chưa có dữ liệu — quay lại sau khi có trận được chấm.</p>
  <?php else : ?>
    <div class="flex flex-wrap gap-6 mb-8">
      <div class="rounded-2xl border border-card bg-card p-5">
        <div class="font-hemi text-4xl text-brand"><?php echo esc_html($bd_s['outcome_pct']); ?>%</div>
        <div class="text-sm text-secondary mt-1">đúng kết quả (1X2) · <?php echo esc_html($bd_s['total']); ?> trận</div>
      </div>
      <div class="rounded-2xl border border-card bg-card p-5">
        <div class="font-hemi text-4xl"><?php echo esc_html($bd_s['score_pct']); ?>%</div>
        <div class="text-sm text-secondary mt-1">trúng tỉ số chính xác (<?php echo esc_html($bd_s['score_correct']); ?>/<?php echo esc_html($bd_s['total']); ?>)</div>
      </div>
    </div>
    <div class="rounded-2xl border border-card bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-secondary text-xs uppercase">
          <tr class="border-b border-card">
            <th class="text-left px-4 py-3">Trận</th>
            <th class="px-3 py-3">Dự đoán</th>
            <th class="px-3 py-3">Kết quả</th>
            <th class="px-3 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bd_s['recent'] as $r) : ?>
            <tr class="border-t border-card">
              <td class="px-4 py-3"><?php echo esc_html($r['home'] . ' – ' . $r['away']); ?></td>
              <td class="text-center px-3 py-3 text-secondary"><?php echo esc_html($r['pred_home'] . '–' . $r['pred_away']); ?></td>
              <td class="text-center px-3 py-3 font-semibold"><?php echo esc_html($r['actual_home'] . '–' . $r['actual_away']); ?></td>
              <td class="text-center px-3 py-3"><?php echo $r['outcome_correct'] ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">✗</span>'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
