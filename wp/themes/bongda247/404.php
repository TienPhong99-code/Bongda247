<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container text-center py-20">
  <h1 class="font-hemi text-6xl uppercase mb-4">404</h1>
  <p class="text-secondary mb-8">Không tìm thấy trang bạn cần.</p>
  <a href="<?php echo esc_url(home_url('/')); ?>"
     class="inline-block px-6 py-3 rounded-full bg-brand text-on-brand font-hemi uppercase text-sm">
    Về trang chủ
  </a>
</div>

<?php get_footer(); ?>
