<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-2">
    Kết quả tìm kiếm cho: "<?php echo esc_html(get_search_query()); ?>"
  </h1>
  <p class="text-secondary text-sm mb-8"><?php echo esc_html($wp_query->found_posts); ?> kết quả</p>

  <?php if (have_posts()) : ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php while (have_posts()) : the_post(); ?>
        <article class="rounded-lg bg-card border border-card overflow-hidden group">
          <a href="<?php echo esc_url(get_permalink()); ?>" class="block">
            <?php if (has_post_thumbnail()) : ?>
              <div class="overflow-hidden aspect-video">
                <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500', 'alt' => the_title_attribute(['echo' => false])]); ?>
              </div>
            <?php endif; ?>
            <div class="p-4">
              <h2 class="text-lg leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php echo esc_html(get_the_title()); ?></h2>
              <p class="text-sm text-secondary mt-2 line-clamp-2"><?php echo esc_html(get_the_excerpt()); ?></p>
              <time class="text-xs text-secondary mt-3 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
            </div>
          </a>
        </article>
      <?php endwhile; ?>
    </div>

    <div class="mt-10 flex justify-center gap-2">
      <?php echo paginate_links(['prev_text' => '‹', 'next_text' => '›']); ?>
    </div>
  <?php else : ?>
    <p class="text-secondary mb-6">Không tìm thấy bài viết nào cho "<?php echo esc_html(get_search_query()); ?>".</p>
    <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="relative max-w-md">
      <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Thử từ khoá khác..."
             class="w-full rounded-lg bg-card border border-card px-4 py-3 pr-11 text-sm focus:outline-none focus:border-brand">
      <button type="submit" aria-label="Tìm" class="absolute right-3 top-1/2 -translate-y-1/2 text-secondary hover:text-brand">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
      </button>
    </form>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
