<?php defined('ABSPATH') || exit; get_header(); ?>

<section>
  <div class="container">
    <div class="row">
      <div class="col col-8">
        <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Tin mới cập nhật</h2>
        <?php get_template_part('template-parts/hot-news-slider'); ?>
      </div>
      <div class="col col-4">
        <?php get_template_part('template-parts/sidebar-slider'); ?>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="container">
    <?php get_template_part('template-parts/match-insights'); ?>
  </div>
</section>

<section>
  <div class="container">
    <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-8">Tin theo giải đấu</h2>
    <?php
    // 3 giải mỗi cột — sửa mảng để thêm/bớt cột.
    $bd_home_categories = ['ngoai-hang-anh', 'la-liga', 'champions-league'];
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <?php foreach ($bd_home_categories as $bd_cat_slug) :
          set_query_var('bd_cat_slug', $bd_cat_slug);
          get_template_part('template-parts/category-column');
      endforeach; ?>
    </div>
  </div>
</section>

<section>
  <div class="container">
    <div class="row">
      <div class="col col-8">
        <?php set_query_var('bd_cat_slug', 'chuyen-nhuong'); get_template_part('template-parts/transfer-list'); ?>
      </div>
      <div class="col col-4">
        <?php set_query_var('bd_fd_widget_slug', 'ngoai-hang-anh'); get_template_part('template-parts/fd-widget'); ?>
      </div>
    </div>
  </div>
</section>

<?php get_footer(); ?>
