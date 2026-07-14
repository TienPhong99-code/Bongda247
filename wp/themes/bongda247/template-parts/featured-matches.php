<?php
defined('ABSPATH') || exit;

$bd_matches = bd_fd_featured_matches(8);
if (empty($bd_matches)) return; // rỗng → ẩn hẳn, không in <section>

$bd_today = wp_date('Y-m-d'); // hôm nay theo giờ site
?>
<section>
  <div class="container">
    <div class="flex items-center justify-between mb-6">
      <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4">Trận đấu nổi bật</h2>
      <a href="<?php echo esc_url(home_url('/lich-thi-dau/')); ?>" class="text-sm text-secondary hover:text-brand whitespace-nowrap ml-4">Xem lịch đầy đủ →</a>
    </div>
    <div class="rounded-2xl border border-card bg-card overflow-hidden">
      <?php foreach ($bd_matches as $bd_m) :
        $bd_finished = ($bd_m['status'] === 'FINISHED');
        $bd_time  = wp_date('H:i', $bd_m['ts']);
        $bd_day   = (wp_date('Y-m-d', $bd_m['ts']) !== $bd_today) ? wp_date('d/m', $bd_m['ts']) : '';
        $bd_score = ($bd_m['sh'] ?? '?') . '–' . ($bd_m['sa'] ?? '?');
        $bd_link  = home_url('/lich-thi-dau/?league=' . $bd_m['league_slug']);
      ?>
        <a href="<?php echo esc_url($bd_link); ?>" class="flex items-center gap-3 px-4 py-2.5 border-t border-card first:border-t-0 hover:bg-control transition-colors text-sm">
          <span class="w-12 shrink-0 <?php echo $bd_finished ? 'text-secondary' : 'text-brand font-semibold'; ?>"><?php echo $bd_finished ? 'FT' : esc_html($bd_time); ?></span>
          <span class="flex-1 flex items-center justify-end gap-2 min-w-0">
            <span class="truncate text-right"><?php echo esc_html($bd_m['home']); ?></span>
            <?php if (!empty($bd_m['homeCrest'])) : ?><img src="<?php echo esc_url($bd_m['homeCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
          </span>
          <span class="shrink-0 font-semibold px-1 tabular-nums"><?php echo $bd_finished ? esc_html($bd_score) : '–'; ?></span>
          <span class="flex-1 flex items-center gap-2 min-w-0">
            <?php if (!empty($bd_m['awayCrest'])) : ?><img src="<?php echo esc_url($bd_m['awayCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
            <span class="truncate"><?php echo esc_html($bd_m['away']); ?></span>
          </span>
          <span class="hidden sm:flex shrink-0 items-center gap-2 text-xs text-secondary w-28 justify-end">
            <span class="truncate"><?php echo esc_html($bd_m['league_name']); ?></span>
            <?php if ($bd_day) : ?><span class="whitespace-nowrap"><?php echo esc_html($bd_day); ?></span><?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
