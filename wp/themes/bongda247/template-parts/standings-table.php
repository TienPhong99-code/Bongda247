<?php
// Bảng xếp hạng giải đấu — nhận data qua set_query_var('bd_fd_rows').
defined('ABSPATH') || exit;
$rows = get_query_var('bd_fd_rows');
if (empty($rows)) return;
?>
<div class="overflow-x-auto rounded-lg border border-card">
  <table class="w-full text-sm min-w-[560px]">
    <thead class="bg-card text-secondary text-xs uppercase">
      <tr>
        <th class="px-3 py-2 text-left">#</th>
        <th class="px-3 py-2 text-left">Đội</th>
        <th class="px-2 py-2 text-center">Trận</th>
        <th class="px-2 py-2 text-center">T</th>
        <th class="px-2 py-2 text-center">H</th>
        <th class="px-2 py-2 text-center">B</th>
        <th class="px-2 py-2 text-center">HS</th>
        <th class="px-2 py-2 text-center font-bold">Đ</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr class="border-t border-card">
          <td class="px-3 py-2 text-secondary"><?php echo esc_html($r['position']); ?></td>
          <td class="px-3 py-2">
            <span class="flex items-center gap-2">
              <?php if (!empty($r['crest'])) : ?><img src="<?php echo esc_url($r['crest']); ?>" alt="" class="w-5 h-5 object-contain" loading="lazy"><?php endif; ?>
              <span class="truncate"><?php echo esc_html($r['name']); ?></span>
            </span>
          </td>
          <td class="px-2 py-2 text-center text-secondary"><?php echo esc_html($r['played']); ?></td>
          <td class="px-2 py-2 text-center"><?php echo esc_html($r['won']); ?></td>
          <td class="px-2 py-2 text-center"><?php echo esc_html($r['draw']); ?></td>
          <td class="px-2 py-2 text-center"><?php echo esc_html($r['lost']); ?></td>
          <td class="px-2 py-2 text-center text-secondary"><?php echo esc_html(($r['gd'] > 0 ? '+' : '') . $r['gd']); ?></td>
          <td class="px-2 py-2 text-center font-bold text-brand"><?php echo esc_html($r['points']); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
