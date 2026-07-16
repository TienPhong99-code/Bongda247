<?php defined('ABSPATH') || exit; get_header();
$bd_range  = (isset($_GET['range']) && $_GET['range'] === 'all') ? 'all' : 'week';
$bd_rows   = bd_leaderboard($bd_range, 50);
$bd_uid    = get_current_user_id();
$bd_myrank = $bd_uid ? bd_user_rank($bd_uid, $bd_range) : 0;
$bd_medal  = ['', '🥇', '🥈', '🥉'];
?>
<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Bảng xếp hạng thành viên</h1>

  <div class="flex gap-2 mb-6">
    <a href="?range=week" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $bd_range === 'week' ? 'bg-brand text-white' : 'border border-card text-secondary hover:border-brand'; ?>">Tuần này</a>
    <a href="?range=all" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $bd_range === 'all' ? 'bg-brand text-white' : 'border border-card text-secondary hover:border-brand'; ?>">Mọi thời đại</a>
  </div>

  <?php if (!$bd_rows) : ?>
    <p class="text-secondary">Chưa có dữ liệu xếp hạng.</p>
  <?php else : ?>
    <div class="rounded-2xl border border-card bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-secondary uppercase text-xs">
          <tr class="border-b border-card">
            <th class="text-left p-3 w-16">Hạng</th>
            <th class="text-left p-3">Thành viên</th>
            <th class="text-right p-3">Điểm</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bd_rows as $bd_r) : $bd_me = ($bd_r['user_id'] === $bd_uid); ?>
            <tr class="border-b border-card/50 <?php echo $bd_me ? 'bg-brand/10 font-semibold' : ''; ?>">
              <td class="p-3"><?php echo $bd_r['rank'] <= 3 ? $bd_medal[$bd_r['rank']] : '#' . (int) $bd_r['rank']; ?></td>
              <td class="p-3"><?php echo esc_html($bd_r['top_badge']); ?> <?php echo esc_html($bd_r['name']); ?><?php if ($bd_r['streak'] > 0) : ?> <span class="text-xs text-secondary">🔥<?php echo (int) $bd_r['streak']; ?></span><?php endif; ?></td>
              <td class="p-3 text-right text-brand font-bold"><?php echo (int) $bd_r['points']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($bd_uid && $bd_myrank > 50) : ?>
      <p class="mt-4 text-sm text-secondary">Hạng của bạn: <span class="text-brand font-bold">#<?php echo (int) $bd_myrank; ?></span></p>
    <?php elseif (!$bd_uid) : ?>
      <p class="mt-4 text-sm text-secondary"><a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="text-brand hover:underline">Đăng nhập</a> để tích điểm và lên bảng xếp hạng.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
