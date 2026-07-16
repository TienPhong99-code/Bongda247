<?php
defined('ABSPATH') || exit;

// Nav nhanh: [label, url, active, icon-inner-paths (Tabler outline, MIT)]
$bd_items = [
    [
        'label'  => 'Trang chủ',
        'url'    => home_url('/'),
        'active' => is_front_page(),
        'icon'   => '<path d="M5 12l-2 0l9 -9l9 9l-2 0"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"/><path d="M10 12h4v4h-4z"/>',
    ],
    [
        'label'  => 'Nhận định',
        'url'    => home_url('/nhan-dinh/'),
        'active' => is_page('nhan-dinh'),
        'icon'   => '<path d="M4 19l16 0"/><path d="M4 15l4 -6l4 2l4 -5l4 4"/>',
    ],
    [
        'label'  => 'Kết quả',
        'url'    => home_url('/ket-qua-bong-da/'),
        'active' => is_page('ket-qua-bong-da'),
        'icon'   => '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 7l4.76 3.45l-1.76 5.55h-6l-1.76 -5.55z"/><path d="M12 7v-4m3 13l2.5 3m-.74 -8.55l3.74 -1.45m-11.44 7.05l-2.56 2.95m.74 -8.55l-3.74 -1.45"/>',
    ],
    [
        'label'  => 'Lịch thi đấu',
        'url'    => home_url('/lich-thi-dau/'),
        'active' => is_page('lich-thi-dau'),
        'icon'   => '<path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M16 3l0 4"/><path d="M8 3l0 4"/><path d="M4 11l16 0"/><path d="M8 15h2v2h-2z"/>',
    ],
    [
        'label'  => 'BXH giải',
        'url'    => home_url('/bang-xep-hang/'),
        'active' => is_page('bang-xep-hang'),
        'icon'   => '<path d="M8 21l8 0"/><path d="M12 17l0 4"/><path d="M7 4l10 0"/><path d="M17 4v8a5 5 0 0 1 -10 0v-8"/><path d="M5 9m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M19 9m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>',
    ],
    [
        'label'  => 'Xếp hạng thành viên',
        'url'    => home_url('/bang-xep-hang-thanh-vien/'),
        'active' => is_page('bang-xep-hang-thanh-vien'),
        'icon'   => '<path d="M9 3h6l3 7l-6 2l-6 -2z"/><path d="M12 12l-3 -9"/><path d="M15 11l-3 -8"/><path d="M12 19.5l-3 1.5l.5 -3.5l-2 -2l3 -.5l1.5 -3l1.5 3l3 .5l-2 2l.5 3.5z"/>',
    ],
];
$bd_star   = '<path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/>';
$bd_flame  = '<path d="M12 10.941c2.333 -3.308 .167 -7.823 -1 -8.941c0 3.395 -2.235 5.299 -3.667 6.706c-1.43 1.408 -2.333 3.621 -2.333 5.588c0 3.704 3.134 6.706 7 6.706s7 -3.002 7 -6.706c0 -1.712 -1.232 -4.403 -2.333 -5.588c-2.084 3.353 -3.257 3.353 -4.667 2.235"/>';
$bd_logout = '<path d="M10 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2"/><path d="M15 12h-12l3 -3"/><path d="M6 15l-3 -3"/>';
$bd_login  = '<path d="M9 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2"/><path d="M3 12h13l-3 -3"/><path d="M13 15l3 -3"/>';
?>
<aside class="hidden lg:flex flex-col fixed left-0 top-16 bottom-0 w-60 border-r border-card bg-card z-40 overflow-y-auto">
  <div class="px-4 pt-4 pb-2 text-xs font-hemi uppercase tracking-wide text-secondary">Điều hướng</div>
  <nav class="flex-1 px-3 space-y-1">
    <?php foreach ($bd_items as $bd_it) : ?>
      <a href="<?php echo esc_url($bd_it['url']); ?>"
         class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $bd_it['active'] ? 'bg-brand/10 text-brand' : 'text-secondary hover:text-brand hover:bg-control'; ?>"
         <?php echo $bd_it['active'] ? 'aria-current="page"' : ''; ?>>
        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_it['icon']; ?></svg>
        <span><?php echo esc_html($bd_it['label']); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mt-auto border-t border-card p-3">
    <?php if (is_user_logged_in()) : $bd_su = wp_get_current_user(); ?>
      <div class="text-sm font-semibold truncate"><?php echo esc_html($bd_su->display_name); ?></div>
      <div class="flex items-center gap-4 text-xs mt-1">
        <span class="inline-flex items-center gap-1 text-brand" title="Điểm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?php echo $bd_star; ?></svg>
          <span data-bd-points-balance><?php echo (int) bd_get_points($bd_su->ID); ?></span>
        </span>
        <span class="inline-flex items-center gap-1 text-secondary" title="Chuỗi điểm danh">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_flame; ?></svg>
          <?php echo (int) get_user_meta($bd_su->ID, 'bd_streak', true); ?>
        </span>
      </div>
      <div class="flex items-center gap-2 mt-3">
        <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="flex-1 text-center text-xs rounded-lg border border-card py-1.5 text-secondary hover:border-brand hover:text-brand transition-colors">Tài khoản</a>
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="rounded-lg border border-card p-1.5 text-secondary hover:border-brand hover:text-brand transition-colors" aria-label="Đăng xuất" title="Đăng xuất">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_logout; ?></svg>
        </a>
      </div>
    <?php else : ?>
      <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="flex items-center justify-center gap-2 rounded-lg bg-brand text-white py-2 text-sm font-medium hover:opacity-90 transition-opacity">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_login; ?></svg>
        Đăng nhập / Đăng ký
      </a>
    <?php endif; ?>
  </div>
</aside>
