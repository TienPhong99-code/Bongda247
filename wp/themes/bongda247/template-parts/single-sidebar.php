<?php
defined('ABSPATH') || exit;
// Sidebar bài chi tiết: tin mới nhất.
// (Ô quảng cáo tạm ẩn — khi có Google AdSense, thêm lại khối <ins class="adsbygoogle">.)
?>
<?php
$bd_latest = new WP_Query([
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post__not_in'        => [get_the_ID()],
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);
if ($bd_latest->have_posts()) : ?>
  <div>
    <h2 class="font-hemi text-lg uppercase border-l-4 border-brand pl-3 mb-4">Tin mới nhất</h2>
    <ul class="space-y-4">
      <?php while ($bd_latest->have_posts()) : $bd_latest->the_post(); ?>
        <li>
          <a href="<?php echo esc_url(get_permalink()); ?>" class="group flex gap-3">
            <?php if (has_post_thumbnail()) : ?>
              <div class="w-20 h-16 shrink-0 rounded-lg overflow-hidden border border-card">
                <?php the_post_thumbnail('bd_thumb', ['class' => 'w-full h-full object-cover', 'alt' => the_title_attribute(['echo' => false])]); ?>
              </div>
            <?php endif; ?>
            <div class="min-w-0">
              <h3 class="text-sm font-medium leading-snug line-clamp-2 group-hover:text-brand transition-colors"><?php the_title(); ?></h3>
              <time class="text-xs text-secondary mt-1 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
            </div>
          </a>
        </li>
      <?php endwhile; wp_reset_postdata(); ?>
    </ul>
  </div>
<?php endif; ?>
