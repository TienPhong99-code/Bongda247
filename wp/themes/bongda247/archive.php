<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-8">
    <?php echo esc_html(single_term_title('', false) ?: get_the_archive_title()); ?>
  </h1>

  <?php if (have_posts()) : ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php while (have_posts()) : the_post(); ?>
        <article class="rounded-lg bg-card border border-card overflow-hidden group">
          <a href="<?php the_permalink(); ?>" class="block">
            <?php if (has_post_thumbnail()) : ?>
              <div class="overflow-hidden aspect-video">
                <?php the_post_thumbnail('bd_hero', [
                    'class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500',
                ]); ?>
              </div>
            <?php endif; ?>
            <div class="p-4">
              <h2 class="text-lg leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php the_title(); ?></h2>
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
    <p class="text-secondary">Chưa có bài viết nào trong mục này.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
