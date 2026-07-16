<?php
defined('ABSPATH') || exit;

$bd_slug = get_query_var('bd_cat_slug');
$bd_term = $bd_slug ? get_category_by_slug($bd_slug) : null;
if (!$bd_term) return;

$bd_q = bd_category_posts($bd_slug, 2); // 2 item cho gọn
if (empty($bd_q->posts)) return;

$bd_posts = $bd_q->posts;
?>
<div class="flex items-center justify-between mb-6">
  <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4"><?php echo esc_html($bd_term->name); ?></h2>
  <a href="<?php echo esc_url(get_category_link($bd_term)); ?>" class="text-sm text-secondary hover:text-brand whitespace-nowrap ml-4">Xem tất cả →</a>
</div>

<div class="grid sm:grid-cols-2 gap-6">
  <?php foreach ($bd_posts as $bd_p) : ?>
    <a href="<?php echo esc_url(get_permalink($bd_p)); ?>" class="block group">
      <?php if (has_post_thumbnail($bd_p)) : ?>
        <div class="rounded-lg overflow-hidden border border-card mb-3 aspect-video">
          <?php echo get_the_post_thumbnail($bd_p, 'bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => esc_attr(get_the_title($bd_p))]); ?>
        </div>
      <?php endif; ?>
      <h3 class="font-oswald text-lg font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_p)); ?></h3>
      <p class="text-secondary text-sm mt-2 line-clamp-2"><?php echo esc_html(get_the_excerpt($bd_p)); ?></p>
      <time class="text-xs text-secondary mt-2 block"><?php echo esc_html(get_the_date('d/m/Y', $bd_p)); ?></time>
    </a>
  <?php endforeach; ?>
</div>
