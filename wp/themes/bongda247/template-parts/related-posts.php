<?php
defined('ABSPATH') || exit;
$bd_id   = get_the_ID();
$bd_cats = wp_get_post_categories($bd_id);
if (!$bd_cats) return;
$bd_rel = new WP_Query([
    'category__in'        => $bd_cats,
    'post__not_in'        => [$bd_id],
    'posts_per_page'      => 10,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);
if (!$bd_rel->post_count) return;
?>
<section class="mt-12">
  <div class="flex items-center justify-between mb-6">
    <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4">Bài viết liên quan</h2>
    <div class="flex gap-2">
      <button class="related-prev p-2 rounded-full border border-card bg-control hover:bg-brand hover:text-on-brand cursor-pointer transition-colors" aria-label="Trước">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
      </button>
      <button class="related-next p-2 rounded-full border border-card bg-control hover:bg-brand hover:text-on-brand cursor-pointer transition-colors" aria-label="Sau">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
      </button>
    </div>
  </div>

  <div class="swiper relatedSwiper">
    <div class="swiper-wrapper">
      <?php while ($bd_rel->have_posts()) : $bd_rel->the_post(); ?>
        <div class="swiper-slide !h-auto">
          <a href="<?php echo esc_url(get_permalink()); ?>" class="block group h-full">
            <?php if (has_post_thumbnail()) : ?>
              <div class="rounded-lg overflow-hidden border border-card mb-3 aspect-video">
                <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => the_title_attribute(['echo' => false])]); ?>
              </div>
            <?php endif; ?>
            <h3 class="font-oswald text-base font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php the_title(); ?></h3>
            <time class="text-xs text-secondary mt-1 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
          </a>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
