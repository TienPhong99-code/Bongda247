<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <?php while (have_posts()) : the_post(); ?>
    <article class="max-w-4xl mx-auto">
      <nav class="flex text-sm mb-8 gap-2 font-medium text-secondary">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="transition-colors hover:text-brand">Trang chủ</a>
        <span>/</span>
        <span class="text-brand"><?php the_title(); ?></span>
      </nav>

      <header class="mb-10">
        <h1 class="text-4xl md:text-5xl font-bold font-oswald leading-tight pb-6 border-b border-card"><?php the_title(); ?></h1>
      </header>

      <div class="p-6 md:p-10 rounded-3xl border border-card shadow-inner bg-card">
        <div class="prose-bd">
          <?php the_content(); ?>
        </div>
      </div>
    </article>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
