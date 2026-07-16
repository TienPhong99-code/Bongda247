<?php
defined('ABSPATH') || exit;
// Sidebar bài chi tiết: BXH giải (theo category) + nhận định sắp tới + tin mới nhất.
// (Ô quảng cáo tạm ẩn — khi có Google AdSense, thêm lại khối <ins class="adsbygoogle">.)

// ─── BXH mini của giải (nếu bài thuộc 1 trong 5 giải VĐQG) ──────────────────
$bd_league_slug = '';
foreach ((array) get_the_category() as $bd_c) {
    if (isset(BD_FD_LEAGUES[$bd_c->slug])) {
        $bd_league_slug = $bd_c->slug;
        break;
    }
}
$bd_rows = $bd_league_slug ? array_slice(bd_fd_standings(BD_FD_LEAGUES[$bd_league_slug]['code']), 0, 6) : [];
if ($bd_rows) : ?>
  <div>
    <h2 class="font-hemi text-lg uppercase border-l-4 border-brand pl-3 mb-4">BXH <?php echo esc_html(BD_FD_LEAGUES[$bd_league_slug]['name']); ?></h2>
    <div class="rounded-xl border border-card bg-card overflow-hidden text-sm">
      <?php foreach ($bd_rows as $bd_r) : ?>
        <div class="flex items-center gap-2 px-3 py-2 border-b border-card last:border-0">
          <span class="w-5 text-center text-xs text-secondary"><?php echo (int) $bd_r['position']; ?></span>
          <?php if (!empty($bd_r['crest'])) : ?>
            <img src="<?php echo esc_url($bd_r['crest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy" decoding="async">
          <?php endif; ?>
          <span class="flex-1 truncate"><?php echo esc_html($bd_r['name']); ?></span>
          <span class="font-bold text-brand"><?php echo (int) $bd_r['points']; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <a href="<?php echo esc_url(home_url('/bang-xep-hang/?league=' . $bd_league_slug)); ?>" class="inline-block mt-2 text-sm text-brand hover:underline">Xem BXH đầy đủ →</a>
  </div>
<?php endif; ?>

<?php
// ─── Nhận định trận sắp tới (tối đa 3) ─────────────────────────────────────
$bd_ins      = bd_insights(8);
$bd_upcoming = [];
if ($bd_ins->have_posts()) {
    while ($bd_ins->have_posts()) {
        $bd_ins->the_post();
        $bd_iid = get_the_ID();
        $bd_mt  = (string) get_post_meta($bd_iid, 'match_time', true);
        $bd_md  = (string) get_post_meta($bd_iid, 'match_date', true);
        if (bd_insight_is_upcoming($bd_mt, $bd_md)) {
            $bd_upcoming[] = [
                'time' => $bd_mt,
                'home' => (string) get_post_meta($bd_iid, 'home_team', true),
                'away' => (string) get_post_meta($bd_iid, 'away_team', true),
            ];
            if (count($bd_upcoming) >= 3) {
                break;
            }
        }
    }
    wp_reset_postdata();
}
if ($bd_upcoming) : ?>
  <div>
    <h2 class="font-hemi text-lg uppercase border-l-4 border-brand pl-3 mb-4">Nhận định sắp tới</h2>
    <ul class="space-y-3">
      <?php foreach ($bd_upcoming as $bd_m) :
          $bd_hl = bd_insight_team_logo($bd_m['home']);
          $bd_al = bd_insight_team_logo($bd_m['away']);
      ?>
        <li>
          <a href="<?php echo esc_url(home_url('/nhan-dinh/')); ?>" class="block rounded-lg border border-card bg-card p-3 hover:border-brand transition-colors">
            <div class="text-xs text-secondary mb-1.5"><?php echo esc_html($bd_m['time']); ?></div>
            <div class="flex items-center gap-2 text-sm font-medium">
              <div class="flex items-center gap-1.5 flex-1 min-w-0">
                <?php if (!empty($bd_hl['crest'])) : ?><img src="<?php echo esc_url($bd_hl['crest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0"><?php endif; ?>
                <span class="truncate"><?php echo esc_html($bd_m['home']); ?></span>
              </div>
              <span class="text-xs text-secondary shrink-0">vs</span>
              <div class="flex items-center gap-1.5 flex-1 min-w-0 justify-end">
                <span class="truncate"><?php echo esc_html($bd_m['away']); ?></span>
                <?php if (!empty($bd_al['crest'])) : ?><img src="<?php echo esc_url($bd_al['crest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0"><?php endif; ?>
              </div>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <a href="<?php echo esc_url(home_url('/nhan-dinh/')); ?>" class="inline-block mt-2 text-sm text-brand hover:underline">Xem tất cả nhận định →</a>
  </div>
<?php endif; ?>

<?php
// ─── Tin mới nhất ──────────────────────────────────────────────────────────
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
              <div class="w-28 h-20 shrink-0 rounded-lg overflow-hidden border border-card">
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
