<?php
defined('ABSPATH') || exit;
$bd_author_id = (int) get_the_author_meta('ID');
$bd_name      = get_the_author();
$bd_bio       = get_the_author_meta('description');
$bd_url       = get_author_posts_url($bd_author_id);
?>
<div class="mt-10 pt-6 border-t border-card flex items-start gap-4">
  <?php echo get_avatar($bd_author_id, 56, '', esc_attr($bd_name), ['class' => 'rounded-full shrink-0']); ?>
  <div class="min-w-0">
    <div class="font-hemi text-lg"><?php echo esc_html($bd_name); ?></div>
    <?php if ($bd_bio) : ?>
      <p class="text-sm text-secondary mt-1 leading-relaxed"><?php echo esc_html($bd_bio); ?></p>
    <?php endif; ?>
    <a href="<?php echo esc_url($bd_url); ?>" class="inline-block mt-2 text-sm text-brand hover:underline">Xem tất cả bài của tác giả →</a>
  </div>
</div>
