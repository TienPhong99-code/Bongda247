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

// Icon khắc giữa huy chương — Tabler Icons (MIT), nhúng thẳng path (không runtime/CDN).
$bd_icons = [
    'rookie'    => '<path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/>',
    'pro'       => '<path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M12 7a5 5 0 1 0 5 5"/><path d="M13 3.055a9 9 0 1 0 7.941 7.945"/><path d="M15 6v3h3l3 -3h-3v-3z"/><path d="M15 9l-3 3"/>',
    'legend'    => '<path d="M12 6l4 6l5 -4l-2 10h-14l-2 -10l5 4z"/>',
    'diligent'  => '<path d="M12 10.941c2.333 -3.308 .167 -7.823 -1 -8.941c0 3.395 -2.235 5.299 -3.667 6.706c-1.43 1.408 -2.333 3.621 -2.333 5.588c0 3.704 3.134 6.706 7 6.706s7 -3.002 7 -6.706c0 -1.712 -1.232 -4.403 -2.333 -5.588c-2.084 3.353 -3.257 3.353 -4.667 2.235"/>',
    'steadfast' => '<path d="M6 5h12l3 5l-8.5 9.5a.7 .7 0 0 1 -1 0l-8.5 -9.5l3 -5"/><path d="M10 12l-2 -2.2l.6 -1"/>',
    'reader'    => '<path d="M16 6h3a1 1 0 0 1 1 1v11a2 2 0 0 1 -4 0v-13a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1v12a3 3 0 0 0 3 3h11"/><path d="M8 8l4 0"/><path d="M8 12l4 0"/><path d="M8 16l4 0"/>',
    'talker'    => '<path d="M3 20l1.3 -3.9c-2.324 -3.437 -1.426 -7.872 2.1 -10.374c3.526 -2.501 8.59 -2.296 11.845 .48c3.255 2.777 3.695 7.266 1.029 10.501c-2.666 3.235 -7.615 4.215 -11.574 2.293l-4.7 1"/>',
    'oracle'    => '<path d="M6.73 17.018a8 8 0 1 1 10.54 0"/><path d="M5 19a2 2 0 0 0 2 2h10a2 2 0 1 0 0 -4h-10a2 2 0 0 0 -2 2z"/><path d="M11 7a3 3 0 0 0 -3 3"/>',
];
$bd_lock = '<path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/>';
?>
<div class="grid grid-cols-4 sm:grid-cols-6 gap-4">
  <?php foreach ($bd_badges as $bd_b) :
      $bd_on   = !empty($bd_b['earned']);
      $bd_c    = $bd_metal[$bd_b['tier']] ?? $bd_metal['bronze'];
      $bd_gid  = 'bdm-' . preg_replace('/[^a-z0-9]/', '', $bd_b['id']);
      $bd_glyph = $bd_on ? ($bd_icons[$bd_b['id']] ?? '') : $bd_lock;
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
        <span class="absolute inset-0 flex items-center justify-center">
          <svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter:drop-shadow(0 1px 1px rgba(0,0,0,.45));" aria-hidden="true"><?php echo $bd_glyph; ?></svg>
        </span>
      </div>
      <span class="mt-1.5 text-[11px] leading-tight <?php echo $bd_on ? 'font-semibold' : 'text-secondary'; ?>"><?php echo esc_html($bd_b['name']); ?></span>
    </div>
  <?php endforeach; ?>
</div>
