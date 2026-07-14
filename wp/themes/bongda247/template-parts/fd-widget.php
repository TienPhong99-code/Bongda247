<?php
defined('ABSPATH') || exit;
$bd_slug = get_query_var('bd_fd_widget_slug') ?: 'ngoai-hang-anh';
?>
<div class="rounded-2xl border border-card bg-card p-4" data-fd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
  <div class="flex items-center justify-between gap-2 mb-3">
    <h3 class="font-hemi text-lg uppercase">Số liệu</h3>
    <select data-fd-league class="text-xs bg-control border border-card rounded px-2 py-1 cursor-pointer">
      <?php foreach (BD_FD_LEAGUES as $slug => $lg) : ?>
        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, $bd_slug); ?>><?php echo esc_html($lg['name']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div data-fd-body>
    <?php // Chuyển tiếp slug đã resolve (kèm default) xuống body — widget tự chứa, không phụ thuộc caller.
    set_query_var('bd_fd_widget_slug', $bd_slug); get_template_part('template-parts/fd-widget-body'); ?>
  </div>
</div>
