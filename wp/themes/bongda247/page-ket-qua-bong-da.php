<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Kết quả bóng đá</h1>

  <?php $bd_groups = bd_fd_results_by_date(7); ?>
  <?php if (!$bd_groups) : ?>
    <p class="text-secondary">Chưa có kết quả trong 7 ngày qua.</p>
  <?php else : ?>
    <?php foreach ($bd_groups as $bd_g) : ?>
      <h2 class="font-hemi text-lg uppercase text-secondary mt-8 mb-3"><?php echo esc_html($bd_g['label']); ?></h2>
      <div class="rounded-2xl border border-card bg-card overflow-hidden">
        <?php foreach ($bd_g['matches'] as $bd_m) :
          $bd_link  = home_url('/lich-thi-dau/?league=' . $bd_m['league_slug']);
          $bd_score = ($bd_m['sh'] ?? '?') . '–' . ($bd_m['sa'] ?? '?');
        ?>
          <a href="<?php echo esc_url($bd_link); ?>" class="flex items-center gap-3 px-4 py-2.5 border-t border-card first:border-t-0 hover:bg-control transition-colors text-sm">
            <span class="w-10 shrink-0 text-secondary text-xs"><?php echo esc_html(wp_date('H:i', $bd_m['ts'])); ?></span>
            <span class="flex-1 flex items-center justify-end gap-2 min-w-0">
              <span class="truncate text-right"><?php echo esc_html($bd_m['home']); ?></span>
              <?php if (!empty($bd_m['homeCrest'])) : ?><img src="<?php echo esc_url($bd_m['homeCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
            </span>
            <span class="shrink-0 font-semibold px-1 tabular-nums"><?php echo esc_html($bd_score); ?></span>
            <span class="flex-1 flex items-center gap-2 min-w-0">
              <?php if (!empty($bd_m['awayCrest'])) : ?><img src="<?php echo esc_url($bd_m['awayCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
              <span class="truncate"><?php echo esc_html($bd_m['away']); ?></span>
            </span>
            <span class="hidden sm:block shrink-0 text-xs text-secondary w-24 text-right truncate"><?php echo esc_html($bd_m['league_name']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
