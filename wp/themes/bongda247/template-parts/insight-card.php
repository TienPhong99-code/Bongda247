<?php
defined('ABSPATH') || exit;
// 1 card nhận định — gọi TRONG vòng lặp the_post() của match_insight.
$bd_id    = get_the_ID();
$bd_flame = get_stylesheet_directory_uri() . '/assets/images/flame.png';
$bd_home  = (string) get_post_meta($bd_id, 'home_team', true);
$bd_away  = (string) get_post_meta($bd_id, 'away_team', true);
$bd_time  = (string) get_post_meta($bd_id, 'match_time', true);
$bd_hot   = (int) get_post_meta($bd_id, 'hot', true) === 1;
$bd_lines = (array) get_post_meta($bd_id, 'insights', true);
$bd_pred  = (string) get_post_meta($bd_id, 'prediction', true);
?>
<div class="group flex gap-4 rounded-lg bg-card p-3 border border-card flex-col h-full">
  <div>
    <div class="flex justify-between items-center mb-4">
      <span class="text-secondary text-sm font-medium inline-flex items-center gap-1.5">
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 7v5l3 3"/></svg>
        <?php echo esc_html($bd_time); ?>
      </span>
      <div class="flex items-center gap-2">
        <?php $bd_emblem = bd_insight_league_emblem($bd_home, $bd_away); if ($bd_emblem) : ?>
          <img src="<?php echo esc_url($bd_emblem); ?>" alt="" width="20" height="20" class="w-5 h-5 object-contain" loading="lazy" decoding="async">
        <?php endif; ?>
        <?php if ($bd_hot) : ?><img src="<?php echo esc_url($bd_flame); ?>" alt="Hot" width="20" height="20"><?php endif; ?>
      </div>
    </div>
    <div class="mb-4">
      <h3 class="font-hemi text-lg flex items-center gap-2 flex-wrap">
        <?php echo bd_insight_team_badge($bd_home); ?>
        <span class="text-secondary text-sm">VS</span>
        <?php echo bd_insight_team_badge($bd_away); ?>
      </h3>
    </div>
    <?php if ($bd_lines) : ?>
      <ul class="space-y-4 mb-2">
        <?php foreach ($bd_lines as $bd_line) : ?>
          <li class="text-sm text-secondary flex items-start gap-3 leading-relaxed">
            <span class="w-1.5 h-1.5 rounded-full bg-brand mt-1.5 shrink-0"></span>
            <?php echo esc_html($bd_line); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php echo bd_prediction_badge($bd_id, $bd_pred); ?>
</div>
