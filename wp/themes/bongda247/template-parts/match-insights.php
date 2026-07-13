<?php
defined('ABSPATH') || exit;
$insights = bd_insights(15);
$flame    = get_stylesheet_directory_uri() . '/assets/images/flame.png';
?>
<section class="py-8">
  <div class="flex items-center justify-between mb-6">
    <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4">Số liệu chuyên sâu</h2>
    <div class="flex gap-2">
      <button class="insight-prev p-2 rounded-full border border-card hover:bg-brand cursor-pointer transition-colors bg-control" aria-label="Trước">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6"></path>
        </svg>
      </button>
      <button class="insight-next p-2 rounded-full border border-card hover:bg-brand cursor-pointer transition-colors bg-control" aria-label="Sau">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m9 18 6-6-6-6"></path>
        </svg>
      </button>
    </div>
  </div>

  <div class="swiper insightSwiper">
    <div class="swiper-wrapper">
      <?php while ($insights->have_posts()) : $insights->the_post();
          $id         = get_the_ID();
          $match_time = (string) get_post_meta($id, 'match_time', true);
          $match_date = (string) get_post_meta($id, 'match_date', true);

          if (!bd_insight_is_upcoming($match_time, $match_date)) {
              continue;
          }

          $home       = (string) get_post_meta($id, 'home_team', true);
          $away       = (string) get_post_meta($id, 'away_team', true);
          $hot        = (int) get_post_meta($id, 'hot', true) === 1;
          $lines      = (array) get_post_meta($id, 'insights', true);
          $prediction = (string) get_post_meta($id, 'prediction', true);
      ?>
        <div class="swiper-slide !h-auto">
          <div class="group flex gap-4 rounded-lg bg-card p-3 border border-card flex-col h-full">
            <div>
              <div class="flex justify-between items-center mb-4">
                <span class="text-secondary text-sm font-medium"><?php echo esc_html($match_time); ?></span>
                <?php if ($hot) : ?>
                  <img src="<?php echo esc_url($flame); ?>" alt="Hot" width="20" height="20">
                <?php endif; ?>
              </div>

              <div class="mb-4">
                <h3 class="font-hemi text-xl flex items-center gap-3">
                  <?php echo esc_html($home); ?> <span>VS</span> <?php echo esc_html($away); ?>
                </h3>
              </div>

              <?php if ($lines) : ?>
                <ul class="space-y-4 mb-2">
                  <?php foreach ($lines as $line) : ?>
                    <li class="text-sm text-secondary flex items-start gap-3 leading-relaxed">
                      <span class="w-1.5 h-1.5 rounded-full bg-brand mt-1.5 shrink-0"></span>
                      <?php echo esc_html($line); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>

            <?php if ($prediction) : ?>
              <div class="inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction">
                <?php echo esc_html($prediction); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
