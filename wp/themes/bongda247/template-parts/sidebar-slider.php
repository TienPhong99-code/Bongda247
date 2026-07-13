<?php
defined('ABSPATH') || exit;
$side = bd_sidebar_posts(10);
?>
<aside class="flex h-full flex-col">
  <h2 class="font-hemi text-lg uppercase mb-4 text-brand flex items-center gap-2">
    <span class="w-2 h-2 bg-brand rounded-full animate-pulse"></span>
    Tin mới nhận
  </h2>
  <div class="flex-1 relative overflow-hidden min-h-[400px]">
    <div class="inset-0 absolute">
      <div class="swiper sidebarSwiper h-full">
        <div class="swiper-wrapper">
          <?php while ($side->have_posts()) : $side->the_post();
              $cats = get_the_category();
              $cat  = $cats[0] ?? null;
          ?>
            <div class="swiper-slide !h-auto">
              <article class="group relative flex gap-4 rounded-lg bg-card p-3 border border-card">
                <a href="<?php the_permalink(); ?>" class="absolute inset-0 z-20"></a>
                <div class="flex gap-3">
                  <div class="w-20 h-20 shrink-0 overflow-hidden rounded-lg bg-slate-800">
                    <?php if (has_post_thumbnail()) : ?>
                      <?php the_post_thumbnail('bd_thumb', [
                          'class' => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500',
                      ]); ?>
                    <?php else : ?>
                      <div class="w-full h-full flex items-center justify-center bg-slate-900">
                        <span class="text-[10px] font-hemi text-white">BONGDA247</span>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="flex flex-col justify-center">
                    <?php if ($cat) : ?>
                      <span class="text-[9px] font-bold text-brand uppercase mb-1"><?php echo esc_html($cat->name); ?></span>
                    <?php endif; ?>
                    <h3 class="text-base leading-snug group-hover:text-brand transition-colors line-clamp-2">
                      <?php the_title(); ?>
                    </h3>
                  </div>
                </div>
              </article>
            </div>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
      </div>
    </div>
  </div>
</aside>
