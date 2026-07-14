<?php
defined('ABSPATH') || exit;
$hot = bd_hot_posts(5);
?>
<div class="relative group">
  <div class="swiper hotSwiper rounded-2xl overflow-hidden border border-card shadow-2xl">
    <div class="swiper-wrapper">
      <?php while ($hot->have_posts()) : $hot->the_post();
          $cats = get_the_category();
          $cat  = $cats[0] ?? null;
      ?>
        <div class="swiper-slide">
          <div class="relative aspect-video">
            <a href="<?php the_permalink(); ?>" class="block absolute inset-0 z-30"></a>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent z-10"></div>

            <?php if (has_post_thumbnail()) : ?>
              <span class="absolute inset-0">
                <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover', 'alt' => the_title_attribute(['echo' => false])]); ?>
              </span>
            <?php else : ?>
              <div class="w-full absolute h-full bg-slate-900 flex items-center justify-center">
                <span class="font-hemi text-slate-700 italic text-4xl">BONGDA247</span>
              </div>
            <?php endif; ?>

            <div class="absolute bottom-0 left-0 p-6 md:p-10 z-20 w-full">
              <?php if ($cat) : ?>
                <span class="inline-block px-3 py-1 rounded-md mb-3 text-[10px] font-bold uppercase font-hemi tracking-wider bg-brand text-white">
                  <?php echo esc_html($cat->name); ?>
                </span>
              <?php endif; ?>
              <h2 class="text-2xl md:text-4xl font-hemi text-white leading-tight mb-3 line-clamp-2 drop-shadow-md">
                <?php the_title(); ?>
              </h2>
              <p class="text-slate-300 text-sm md:text-base line-clamp-2 hidden md:block opacity-90">
                <?php echo esc_html(get_the_excerpt()); ?>
              </p>
            </div>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <div class="swiper-pagination !bottom-4"></div>
  </div>
</div>
