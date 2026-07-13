<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-8">
    <?php echo esc_html(get_the_archive_title() ?: 'Tin tức'); ?>
  </h1>

  <?php if (have_posts()) : ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <?php while (have_posts()) : the_post(); ?>
        <article class="rounded-lg bg-card border border-card overflow-hidden">
          <a href="<?php the_permalink(); ?>" class="block">
            <?php if (has_post_thumbnail()) : ?>
              <?php the_post_thumbnail('bd_hero', ['class' => 'w-full aspect-video object-cover']); ?>
            <?php endif; ?>
            <div class="p-4">
              <h2 class="text-lg leading-snug hover:text-brand transition-colors"><?php the_title(); ?></h2>
              <p class="text-sm text-secondary mt-2 line-clamp-2"><?php echo esc_html(get_the_excerpt()); ?></p>
            </div>
          </a>
        </article>
      <?php endwhile; ?>
    </div>
  <?php else : ?>
    <p class="text-secondary">Chưa có bài viết nào.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
