<?php
defined('ABSPATH') || exit;
$bd_uid    = (int) ($args['uid'] ?? get_current_user_id());
$bd_badges = bd_user_badges($bd_uid);
// gradient vòng ngoài theo tier (chuỗi literal để Tailwind scan thấy)
$bd_ring = [
    'bronze' => 'from-amber-700 to-amber-400',
    'silver' => 'from-slate-500 to-slate-200',
    'gold'   => 'from-yellow-600 to-yellow-300',
    'brand'  => 'from-brand to-blue-400',
];
?>
<div class="grid grid-cols-4 sm:grid-cols-6 gap-4">
  <?php foreach ($bd_badges as $bd_b) :
      $bd_earned = !empty($bd_b['earned']);
      $bd_grad   = $bd_ring[$bd_b['tier']] ?? $bd_ring['bronze'];
  ?>
    <div class="flex flex-col items-center text-center <?php echo $bd_earned ? '' : 'opacity-40 grayscale'; ?>" title="<?php echo esc_attr($bd_b['desc']); ?>">
      <div class="w-14 h-14 rounded-full p-[3px] bg-gradient-to-br <?php echo esc_attr($bd_grad); ?>">
        <div class="w-full h-full rounded-full bg-card flex items-center justify-center text-2xl">
          <?php echo $bd_earned ? esc_html($bd_b['icon']) : '🔒'; ?>
        </div>
      </div>
      <span class="mt-1 text-[11px] leading-tight text-secondary"><?php echo esc_html($bd_b['name']); ?></span>
    </div>
  <?php endforeach; ?>
</div>
