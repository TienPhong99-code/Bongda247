<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Tài khoản</h1>

  <?php if (is_user_logged_in()) :
    $bd_u = wp_get_current_user(); ?>
    <div class="max-w-md rounded-2xl border border-card bg-card p-6">
      <div class="font-hemi text-xl mb-3"><?php echo esc_html($bd_u->display_name); ?></div>
      <p class="text-sm text-secondary">Email: <?php echo esc_html($bd_u->user_email); ?></p>
      <p class="text-sm text-secondary">Tham gia: <?php echo esc_html(date_i18n('d/m/Y', strtotime($bd_u->user_registered))); ?></p>
      <p class="text-sm text-secondary mt-2">Điểm: <span class="text-brand font-bold"><?php echo (int) bd_get_points($bd_u->ID); ?></span></p>
      <?php
      $bd_today      = current_time('Y-m-d');
      $bd_done_today = ((string) get_user_meta($bd_u->ID, 'bd_checkin_last', true) === $bd_today);
      $bd_streak     = (int) get_user_meta($bd_u->ID, 'bd_streak', true);
      $bd_qs         = bd_quest_state($bd_u->ID);
      $bd_rank_all   = bd_user_rank($bd_u->ID, 'all');
      ?>
      <div class="mt-4 pt-4 border-t border-card" data-bd-checkin-box data-bd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-bd-nonce="<?php echo esc_attr(wp_create_nonce('bd_points')); ?>">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-sm">🔥 Chuỗi <span data-bd-streak class="font-bold text-brand"><?php echo $bd_streak; ?></span> ngày</span>
          <button data-bd-checkin type="button" <?php echo $bd_done_today ? 'disabled' : ''; ?> class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors <?php echo $bd_done_today ? 'border border-card text-secondary cursor-default' : 'bg-brand text-white hover:opacity-90'; ?>">
            <?php echo $bd_done_today ? 'Đã điểm danh hôm nay ✓' : 'Điểm danh hôm nay (+2đ)'; ?>
          </button>
        </div>

        <div class="text-sm font-hemi uppercase text-secondary mt-4 mb-2">Nhiệm vụ hôm nay</div>
        <ul class="space-y-1 text-sm">
          <?php foreach (BD_QUESTS as $bd_qk => $bd_q) :
              $bd_prog  = (int) ($bd_qs['progress'][$bd_qk] ?? 0);
              $bd_qdone = !empty($bd_qs['done'][$bd_qk]);
          ?>
            <li class="flex items-center justify-between">
              <span class="<?php echo $bd_qdone ? 'text-brand' : 'text-secondary'; ?>"><?php echo $bd_qdone ? '✓' : '○'; ?> <?php echo esc_html($bd_q['label']); ?> (+<?php echo (int) $bd_q['reward']; ?>đ)</span>
              <span class="text-xs text-secondary"><?php echo min($bd_prog, (int) $bd_q['target']); ?>/<?php echo (int) $bd_q['target']; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="text-sm font-hemi uppercase text-secondary mt-4 mb-2">Huy hiệu</div>
        <?php get_template_part('template-parts/badge-grid', null, ['uid' => $bd_u->ID]); ?>

        <a href="<?php echo esc_url(home_url('/bang-xep-hang-thanh-vien/')); ?>" class="inline-block mt-4 text-sm text-brand hover:underline">🏆 Bảng xếp hạng thành viên<?php echo $bd_rank_all ? ' — Hạng của bạn: #' . (int) $bd_rank_all : ''; ?></a>
      </div>
      <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="inline-block mt-4 text-sm text-brand hover:underline">Đăng xuất</a>
    </div>
  <?php else :
    $bd_err  = isset($_GET['auth_error']) ? sanitize_key($_GET['auth_error']) : '';
    $bd_msgs = [
      'email'       => 'Email không hợp lệ.',
      'weakpass'    => 'Mật khẩu phải từ 8 ký tự trở lên.',
      'emailexists' => 'Email này đã được đăng ký.',
      'regfail'     => 'Đăng ký không thành công, vui lòng thử lại.',
      'login'       => 'Email hoặc mật khẩu không đúng.',
    ];
  ?>
    <?php if ($bd_err && isset($bd_msgs[$bd_err])) : ?>
      <div class="max-w-3xl rounded-lg border border-red-500/40 bg-red-500/10 text-red-500 px-4 py-3 mb-6 text-sm"><?php echo esc_html($bd_msgs[$bd_err]); ?></div>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 gap-6 max-w-3xl">
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rounded-2xl border border-card bg-card p-6">
        <h2 class="font-hemi text-lg uppercase mb-4">Đăng nhập</h2>
        <input type="hidden" name="action" value="bd_login">
        <?php wp_nonce_field('bd_login'); ?>
        <label class="block text-sm text-secondary mb-1">Email hoặc tên đăng nhập</label>
        <input type="text" name="login" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-3 text-sm focus:outline-none focus:border-brand">
        <label class="block text-sm text-secondary mb-1">Mật khẩu</label>
        <input type="password" name="password" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-4 text-sm focus:outline-none focus:border-brand">
        <button type="submit" class="w-full rounded-lg bg-brand text-white py-2 text-sm font-medium hover:opacity-90 transition-opacity">Đăng nhập</button>
      </form>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rounded-2xl border border-card bg-card p-6">
        <h2 class="font-hemi text-lg uppercase mb-4">Đăng ký</h2>
        <input type="hidden" name="action" value="bd_register">
        <?php wp_nonce_field('bd_register'); ?>
        <label class="block text-sm text-secondary mb-1">Tên hiển thị</label>
        <input type="text" name="display_name" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-3 text-sm focus:outline-none focus:border-brand">
        <label class="block text-sm text-secondary mb-1">Email</label>
        <input type="email" name="email" required class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-3 text-sm focus:outline-none focus:border-brand">
        <label class="block text-sm text-secondary mb-1">Mật khẩu (≥ 8 ký tự)</label>
        <input type="password" name="password" required minlength="8" class="w-full rounded-lg bg-control border border-card px-3 py-2 mb-4 text-sm focus:outline-none focus:border-brand">
        <button type="submit" class="w-full rounded-lg bg-brand text-white py-2 text-sm font-medium hover:opacity-90 transition-opacity">Đăng ký</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
