<?php defined('ABSPATH') || exit; ?>
</main>

<footer class="border-t border-card py-10">
  <div class="container">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
      <span class="font-hemi text-xl uppercase">
        BONGDA<span class="text-brand">247</span>
      </span>

      <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-secondary">
        <?php
        $bd_footer_pages = [
          'gioi-thieu'         => 'Giới thiệu',
          'lien-he'            => 'Liên hệ',
          'chinh-sach-bao-mat' => 'Chính sách bảo mật',
          'dieu-khoan'         => 'Điều khoản',
        ];
        foreach ($bd_footer_pages as $bd_slug => $bd_label) :
          $bd_page = get_page_by_path($bd_slug);
          if ($bd_page) : ?>
            <a href="<?php echo esc_url(get_permalink($bd_page)); ?>" class="transition-colors hover:text-brand"><?php echo esc_html($bd_label); ?></a>
        <?php endif; endforeach; ?>
      </nav>

      <p class="text-sm text-secondary">
        © <?php echo esc_html(date('Y')); ?> Bongda247. Tin tức và nhận định bóng đá.
      </p>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
