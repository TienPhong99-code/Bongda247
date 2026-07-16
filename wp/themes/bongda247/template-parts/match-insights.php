<?php
defined('ABSPATH') || exit;
// Số insight tối đa hiển thị trên carousel trang chủ — bd_insights() lấy dư ứng viên hơn
// con số này (xem inc/query.php) để lọc hạn xong vẫn còn đủ bài hợp lệ mà cắt.
$max_insights = 15;
$insights     = bd_insights($max_insights);
$flame        = get_stylesheet_directory_uri() . '/assets/images/flame.png';
$rendered     = 0;
?>
<section class="py-8">
  <div class="flex items-center justify-between mb-6">
    <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4">Số liệu chuyên sâu</h2>
    <div class="flex gap-2">
      <button class="insight-prev p-2 rounded-full border border-card hover:bg-brand hover:text-on-brand cursor-pointer transition-colors bg-control" aria-label="Trước">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6"></path>
        </svg>
      </button>
      <button class="insight-next p-2 rounded-full border border-card hover:bg-brand hover:text-on-brand cursor-pointer transition-colors bg-control" aria-label="Sau">
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
          // Ứng viên đã lấy dư ở bd_insights() — đủ số hợp lệ cần thì dừng, không render thêm.
          if ($rendered >= $max_insights) {
              break;
          }

          $id         = get_the_ID();
          $match_time = (string) get_post_meta($id, 'match_time', true);
          $match_date = (string) get_post_meta($id, 'match_date', true);

          if (!bd_insight_is_upcoming($match_time, $match_date)) {
              continue;
          }

          $rendered++;

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
                <span class="text-secondary text-sm font-medium inline-flex items-center gap-1.5">
                  <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 7v5l3 3"/></svg>
                  <?php echo esc_html($match_time); ?>
                </span>
                <div class="flex items-center gap-2">
                  <?php $bd_emblem = bd_insight_league_emblem($home, $away); if ($bd_emblem) : ?>
                    <span class="inline-flex items-center rounded-sm bg-white px-1 py-0.5 shrink-0">
                      <img src="<?php echo esc_url($bd_emblem); ?>" alt="" class="h-3.5 w-auto object-contain" loading="lazy" decoding="async">
                    </span>
                  <?php endif; ?>
                  <?php if ($hot) : ?>
                    <img src="<?php echo esc_url($flame); ?>" alt="Hot" width="20" height="20">
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-4">
                <h3 class="font-hemi text-lg flex items-center gap-2 flex-wrap">
                  <?php echo bd_insight_team_badge($home); ?>
                  <span class="text-secondary text-sm">VS</span>
                  <?php echo bd_insight_team_badge($away); ?>
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

            <?php echo bd_prediction_badge($id, $prediction); ?>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
</section>
