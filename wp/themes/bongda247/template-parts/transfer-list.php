<?php
defined('ABSPATH') || exit;

$bd_slug = get_query_var('bd_cat_slug');
$bd_term = $bd_slug ? get_category_by_slug($bd_slug) : null;
if (!$bd_term) return;

$bd_q = bd_category_posts($bd_slug, 6);
if (empty($bd_q->posts)) return;

$bd_posts = $bd_q->posts;
$bd_lead  = $bd_posts[0];
$bd_rest  = array_slice($bd_posts, 1); // tối đa 5 tin phụ
?>
<div class="flex items-center justify-between mb-6">
  <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4"><?php echo esc_html($bd_term->name); ?></h2>
  <a href="<?php echo esc_url(get_category_link($bd_term)); ?>" class="text-sm text-secondary hover:text-brand whitespace-nowrap ml-4">Xem tất cả →</a>
</div>

<a href="<?php echo esc_url(get_permalink($bd_lead)); ?>" class="block group mb-5">
  <?php if (has_post_thumbnail($bd_lead)) : ?>
    <div class="rounded-2xl overflow-hidden border border-card mb-3 aspect-video">
      <?php echo get_the_post_thumbnail($bd_lead, 'bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => esc_attr(get_the_title($bd_lead))]); ?>
    </div>
  <?php endif; ?>
  <h3 class="font-oswald text-xl font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_lead)); ?></h3>
  <p class="text-secondary text-sm mt-2 line-clamp-2"><?php echo esc_html(get_the_excerpt($bd_lead)); ?></p>
</a>

<?php if ($bd_rest) : ?>
  <ul class="grid sm:grid-cols-2 gap-x-6">
    <?php foreach ($bd_rest as $bd_p) : ?>
      <li class="py-2 border-t border-card">
        <a href="<?php echo esc_url(get_permalink($bd_p)); ?>" class="block group">
          <h4 class="text-sm font-medium leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title($bd_p)); ?></h4>
          <time class="text-xs text-secondary"><?php echo esc_html(get_the_date('d/m/Y', $bd_p)); ?></time>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
