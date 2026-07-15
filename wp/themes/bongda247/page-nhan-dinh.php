<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Nhận định bóng đá</h1>

  <?php
  // Khối trên: nhận định trận sắp tới (CPT), lọc hạn như carousel trang chủ.
  $bd_q = bd_insights(8);
  ob_start();
  $bd_shown = 0;
  while ($bd_q->have_posts() && $bd_shown < 8) : $bd_q->the_post();
      $bd_mt = (string) get_post_meta(get_the_ID(), 'match_time', true);
      $bd_md = (string) get_post_meta(get_the_ID(), 'match_date', true);
      if (!bd_insight_is_upcoming($bd_mt, $bd_md)) continue;
      $bd_shown++;
      get_template_part('template-parts/insight-card');
  endwhile;
  wp_reset_postdata();
  $bd_cards = ob_get_clean();
  ?>
  <?php if ($bd_shown) : ?>
    <h2 class="font-hemi text-lg uppercase text-secondary mb-3">Nhận định trận sắp tới</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10"><?php echo $bd_cards; ?></div>
  <?php endif; ?>

  <?php
  // Khối dưới: bài phân tích đầy đủ (tag nhan-dinh).
  $bd_articles = new WP_Query([
      'tag'                 => 'nhan-dinh',
      'posts_per_page'      => 8,
      'ignore_sticky_posts' => true,
      'no_found_rows'       => true,
  ]);
  ?>
  <?php if ($bd_articles->post_count) : ?>
    <h2 class="font-hemi text-lg uppercase text-secondary mb-3">Bài phân tích</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php while ($bd_articles->have_posts()) : $bd_articles->the_post(); ?>
        <a href="<?php echo esc_url(get_permalink()); ?>" class="block group">
          <?php if (has_post_thumbnail()) : ?>
            <div class="rounded-2xl overflow-hidden border border-card mb-3 aspect-video">
              <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => the_title_attribute(['echo' => false])]); ?>
            </div>
          <?php endif; ?>
          <h3 class="font-oswald text-lg font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php the_title(); ?></h3>
          <time class="text-xs text-secondary mt-1 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
        </a>
      <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
  <?php endif; ?>

  <?php if (!$bd_shown && !$bd_articles->post_count) : ?>
    <p class="text-secondary">Chưa có nhận định — quay lại sau khi có trận sắp diễn ra.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
