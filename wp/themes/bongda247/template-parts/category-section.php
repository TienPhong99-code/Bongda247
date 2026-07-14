<?php
defined('ABSPATH') || exit;

// Slug truyền từ front-page qua set_query_var('bd_cat_slug', ...).
$bd_slug = get_query_var('bd_cat_slug');
$bd_term = $bd_slug ? get_category_by_slug($bd_slug) : null;
if (!$bd_term) {
    return; // category không tồn tại → ẩn block
}

$bd_q = bd_category_posts($bd_slug, 5);
if (empty($bd_q->posts)) {
    return; // 0 bài → ẩn block
}

$bd_posts = $bd_q->posts;
$bd_lead  = $bd_posts[0];
$bd_rest  = array_slice($bd_posts, 1); // tối đa 4 tin phụ
?>

<section class="mb-12">
  <div class="container">
    <div class="flex items-center justify-between mb-6">
      <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4"><?php echo esc_html($bd_term->name); ?></h2>
      <a href="<?php echo esc_url(get_category_link($bd_term)); ?>" class="text-sm text-secondary hover:text-brand whitespace-nowrap ml-4">Xem tất cả ›</a>
    </div>

    <div class="row">
      <div class="col col-8">
        <a href="<?php echo esc_url(get_permalink($bd_lead)); ?>" class="block group">
          <?php if (has_post_thumbnail($bd_lead)) : ?>
            <div class="rounded-2xl overflow-hidden border border-card mb-4">
              <?php echo get_the_post_thumbnail($bd_lead, 'bd_hero', ['class' => 'w-full h-auto object-cover transition-transform group-hover:scale-105']); ?>
            </div>
          <?php endif; ?>
          <h3 class="font-oswald text-2xl font-bold leading-tight mb-2 group-hover:text-brand transition-colors"><?php echo esc_html(get_the_title($bd_lead)); ?></h3>
          <p class="text-secondary text-sm mb-2"><?php echo esc_html(get_the_excerpt($bd_lead)); ?></p>
          <time class="text-xs text-secondary"><?php echo esc_html(get_the_date('d/m/Y', $bd_lead)); ?></time>
        </a>
      </div>

      <?php if ($bd_rest) : ?>
        <div class="col col-4">
          <ul>
            <?php foreach ($bd_rest as $bd_p) : ?>
              <li class="py-3 border-t border-card first:border-t-0 first:pt-0">
                <a href="<?php echo esc_url(get_permalink($bd_p)); ?>" class="block group">
                  <h4 class="font-medium leading-snug group-hover:text-brand transition-colors"><?php echo esc_html(get_the_title($bd_p)); ?></h4>
                  <time class="text-xs text-secondary"><?php echo esc_html(get_the_date('d/m/Y', $bd_p)); ?></time>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
