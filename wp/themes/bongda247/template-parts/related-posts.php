<?php
defined('ABSPATH') || exit;
$bd_id   = get_the_ID();
$bd_cats = wp_get_post_categories($bd_id);
if (!$bd_cats) return;
$bd_rel = new WP_Query([
    'category__in'        => $bd_cats,
    'post__not_in'        => [$bd_id],
    'posts_per_page'      => 3,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);
if (!$bd_rel->post_count) return;
?>
<section class="max-w-4xl mx-auto mt-12">
  <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Bài viết liên quan</h2>
  <div class="grid sm:grid-cols-3 gap-6">
    <?php while ($bd_rel->have_posts()) : $bd_rel->the_post(); ?>
      <a href="<?php echo esc_url(get_permalink()); ?>" class="block group">
        <?php if (has_post_thumbnail()) : ?>
          <div class="rounded-lg overflow-hidden border border-card mb-3 aspect-video">
            <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => the_title_attribute(['echo' => false])]); ?>
          </div>
        <?php endif; ?>
        <h3 class="font-oswald text-base font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php the_title(); ?></h3>
        <time class="text-xs text-secondary mt-1 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
      </a>
    <?php endwhile; wp_reset_postdata(); ?>
  </div>
</section>
