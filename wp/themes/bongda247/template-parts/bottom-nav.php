<?php
defined('ABSPATH') || exit;

// Bottom tab bar — CHỈ mobile (lg:hidden). Quick-nav 5 mục dùng nhiều nhất.
// Icon Tabler outline (MIT) nhúng thẳng.
$bd_tabs = [
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
        'label'  => 'Lịch',
        'url'    => home_url('/lich-thi-dau/'),
        'active' => is_page('lich-thi-dau'),
        'icon'   => '<path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/><path d="M16 3l0 4"/><path d="M8 3l0 4"/><path d="M4 11l16 0"/><path d="M8 15h2v2h-2z"/>',
    ],
    [
        'label'  => 'Tài khoản',
        'url'    => home_url('/tai-khoan/'),
        'active' => is_page('tai-khoan'),
        'icon'   => '<path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>',
    ],
];
?>
<nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 border-t border-card bg-card flex" aria-label="Điều hướng nhanh">
  <?php foreach ($bd_tabs as $bd_t) : ?>
    <a href="<?php echo esc_url($bd_t['url']); ?>"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium transition-colors <?php echo $bd_t['active'] ? 'text-brand' : 'text-secondary hover:text-brand'; ?>"
       <?php echo $bd_t['active'] ? 'aria-current="page"' : ''; ?>>
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bd_t['icon']; ?></svg>
      <span><?php echo esc_html($bd_t['label']); ?></span>
    </a>
  <?php endforeach; ?>
</nav>
