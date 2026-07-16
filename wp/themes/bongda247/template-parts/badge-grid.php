<?php
defined('ABSPATH') || exit;
$bd_uid    = (int) ($args['uid'] ?? get_current_user_id());
$bd_badges = bd_user_badges($bd_uid);

// Bảng màu kim loại theo hạng: [sáng, sáng-vừa, giữa, tối, viền]
$bd_metal = [
    'bronze' => ['#ffedd5', '#fdba74', '#e0791a', '#b45309', '#7c2d12'],
    'silver' => ['#ffffff', '#f1f5f9', '#cbd5e1', '#64748b', '#475569'],
    'gold'   => ['#fffbe6', '#fde047', '#eab308', '#a16207', '#854d0e'],
    'brand'  => ['#dbe4ff', '#93b4ff', '#3b6bff', '#0232ff', '#001a80'],
];
?>
<div class="grid grid-cols-4 sm:grid-cols-6 gap-4">
  <?php foreach ($bd_badges as $bd_b) :
      $bd_on  = !empty($bd_b['earned']);
      $bd_c   = $bd_metal[$bd_b['tier']] ?? $bd_metal['bronze'];
      $bd_gid = 'bdm-' . preg_replace('/[^a-z0-9]/', '', $bd_b['id']);
      // đổ bóng nổi khối; hạng gold/brand thêm quầng sáng khi đã đạt
      $bd_shadow = ($bd_on && in_array($bd_b['tier'], ['gold', 'brand'], true))
          ? 'filter:drop-shadow(0 0 5px ' . $bd_c[1] . 'cc) drop-shadow(0 2px 2px rgba(0,0,0,.32));'
          : 'filter:drop-shadow(0 2px 2px rgba(0,0,0,.30));';
  ?>
    <div class="flex flex-col items-center text-center" title="<?php echo esc_attr($bd_b['name'] . ' — ' . $bd_b['desc']); ?>">
      <div class="relative w-16 h-16 <?php echo $bd_on ? '' : 'grayscale opacity-40'; ?>">
        <svg viewBox="0 0 64 64" class="w-full h-full" style="<?php echo esc_attr($bd_shadow); ?>" aria-hidden="true">
          <defs>
            <radialGradient id="<?php echo esc_attr($bd_gid); ?>-face" cx="38%" cy="30%" r="78%">
              <stop offset="0%" stop-color="<?php echo esc_attr($bd_c[0]); ?>"/>
              <stop offset="34%" stop-color="<?php echo esc_attr($bd_c[1]); ?>"/>
              <stop offset="70%" stop-color="<?php echo esc_attr($bd_c[2]); ?>"/>
              <stop offset="100%" stop-color="<?php echo esc_attr($bd_c[3]); ?>"/>
            </radialGradient>
            <linearGradient id="<?php echo esc_attr($bd_gid); ?>-rim" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="<?php echo esc_attr($bd_c[1]); ?>"/>
              <stop offset="50%" stop-color="<?php echo esc_attr($bd_c[3]); ?>"/>
              <stop offset="100%" stop-color="<?php echo esc_attr($bd_c[4]); ?>"/>
            </linearGradient>
          </defs>
          <!-- vành ngoài (cạnh xu) -->
          <circle cx="32" cy="32" r="31" fill="url(#<?php echo esc_attr($bd_gid); ?>-rim)"/>
          <!-- răng khía quanh vành -->
          <circle cx="32" cy="32" r="28.5" fill="none" stroke="<?php echo esc_attr($bd_c[4]); ?>" stroke-width="1.4" stroke-dasharray="1.6 2.2" opacity="0.55"/>
          <!-- mặt huy chương -->
          <circle cx="32" cy="32" r="25" fill="url(#<?php echo esc_attr($bd_gid); ?>-face)" stroke="<?php echo esc_attr($bd_c[4]); ?>" stroke-width="1"/>
          <!-- vòng bevel trong -->
          <circle cx="32" cy="32" r="21.5" fill="none" stroke="#ffffff" stroke-opacity="0.28" stroke-width="1.4"/>
          <!-- ánh bóng góc trên -->
          <ellipse cx="24.5" cy="21" rx="14" ry="7.5" fill="#ffffff" opacity="0.36"/>
        </svg>
        <span class="absolute inset-0 flex items-center justify-center text-2xl" style="filter:drop-shadow(0 1px 1px rgba(0,0,0,.4));">
          <?php echo $bd_on ? esc_html($bd_b['icon']) : '🔒'; ?>
        </span>
      </div>
      <span class="mt-1.5 text-[11px] leading-tight <?php echo $bd_on ? 'font-semibold' : 'text-secondary'; ?>"><?php echo esc_html($bd_b['name']); ?></span>
    </div>
  <?php endforeach; ?>
</div>
