<?php
defined('ABSPATH') || exit;

// Slug truyền từ front-page qua set_query_var('bd_cat_slug', ...).
$bd_slug = get_query_var('bd_cat_slug');
$bd_term = $bd_slug ? get_category_by_slug($bd_slug) : null;
if (!$bd_term) {
    return; // category không tồn tại → ẩn cột
}

$bd_q = bd_category_posts($bd_slug, 3);
if (empty($bd_q->posts)) {
    return; // 0 bài → ẩn cột
}

$bd_posts = $bd_q->posts;
$bd_lead  = $bd_posts[0];
$bd_rest  = array_slice($bd_posts, 1); // tối đa 2 tin phụ
?>

<div>
  <a href="<?php echo esc_url(get_category_link($bd_term)); ?>"
     class="block text-sm font-bold uppercase tracking-wide text-brand mb-4 hover:underline">
    <?php echo esc_html($bd_term->name); ?>
  </a>

  <a href="<?php echo esc_url(get_permalink($bd_lead)); ?>" class="block group mb-4">
    <?php if (has_post_thumbnail($bd_lead)) : ?>
      <div class="rounded-lg overflow-hidden border border-card mb-3 aspect-video">
        <?php echo get_the_post_thumbnail($bd_lead, 'bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105']); ?>
      </div>
    <?php endif; ?>
    <h3 class="font-oswald text-lg font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_lead)); ?></h3>
  </a>

  <?php if ($bd_rest) : ?>
    <ul class="space-y-2">
      <?php foreach ($bd_rest as $bd_p) : ?>
        <li class="flex gap-2 text-sm">
          <span class="text-brand leading-6">▪</span>
          <a href="<?php echo esc_url(get_permalink($bd_p)); ?>" class="text-secondary hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_p)); ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
