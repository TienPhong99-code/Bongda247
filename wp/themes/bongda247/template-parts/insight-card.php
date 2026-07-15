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
      <span class="text-secondary text-sm font-medium"><?php echo esc_html($bd_time); ?></span>
      <?php if ($bd_hot) : ?><img src="<?php echo esc_url($bd_flame); ?>" alt="Hot" width="20" height="20"><?php endif; ?>
    </div>
    <div class="mb-4">
      <h3 class="font-hemi text-xl flex items-center gap-3"><?php echo esc_html($bd_home); ?> <span>VS</span> <?php echo esc_html($bd_away); ?></h3>
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
