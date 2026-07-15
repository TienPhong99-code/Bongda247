<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <?php while (have_posts()) : the_post();
      $cats = get_the_category();
      $cat  = $cats[0] ?? null;
      $tags = get_the_tags();
  ?>
    <article class="max-w-4xl mx-auto">
      <nav class="flex text-sm mb-8 gap-2 font-medium text-secondary">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="transition-colors hover:text-brand">Trang chủ</a>
        <?php if ($cat) : ?>
          <span>/</span>
          <a href="<?php echo esc_url(get_category_link($cat)); ?>" class="hover:underline text-brand">
            <?php echo esc_html($cat->name); ?>
          </a>
        <?php endif; ?>
      </nav>

      <header class="mb-10">
        <h1 class="text-4xl md:text-5xl font-bold font-oswald leading-tight mb-6"><?php the_title(); ?></h1>

        <div class="flex items-center gap-4 text-sm pb-6 text-secondary border-b border-card">
          <?php if ($cat) : ?>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-brand/15 text-brand">
              <?php echo esc_html($cat->name); ?>
            </span>
          <?php endif; ?>
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
          <?php if (get_the_modified_time('U') > get_the_date('U') + HOUR_IN_SECONDS) : ?>
            <time datetime="<?php echo esc_attr(get_the_modified_date('c')); ?>" class="text-xs">Cập nhật: <?php echo esc_html(get_the_modified_date('d/m/Y')); ?></time>
          <?php endif; ?>
        </div>
      </header>

      <?php if (has_post_thumbnail()) : ?>
        <div class="mb-12 rounded-2xl overflow-hidden shadow-2xl border border-card">
          <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-auto object-cover', 'alt' => the_title_attribute(['echo' => false])]); ?>
        </div>
      <?php endif; ?>

      <div class="p-6 md:p-10 rounded-3xl border border-card shadow-inner bg-card">
        <?php $bd_c = bd_toc(apply_filters('the_content', get_the_content())); ?>
        <?php if (count($bd_c['items']) >= 3) : ?>
          <nav class="mb-8 p-5 rounded-2xl border border-card bg-control" aria-label="Mục lục">
            <div class="font-hemi text-sm uppercase text-secondary mb-3">Mục lục</div>
            <ol class="space-y-2 list-decimal list-inside">
              <?php foreach ($bd_c['items'] as $bd_item) : ?>
                <li><a href="#<?php echo esc_attr($bd_item['id']); ?>" class="text-sm text-secondary hover:text-brand"><?php echo esc_html($bd_item['text']); ?></a></li>
              <?php endforeach; ?>
            </ol>
          </nav>
        <?php endif; ?>
        <div class="prose-bd">
          <?php echo $bd_c['content']; ?>
        </div>

        <?php if ($tags) : ?>
          <div class="mt-10 pt-6 flex flex-wrap gap-2 border-t border-card">
            <?php foreach ($tags as $tag) : ?>
              <a href="<?php echo esc_url(get_tag_link($tag)); ?>"
                 class="text-sm transition-colors text-secondary hover:text-brand">
                #<?php echo esc_html($tag->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php
        $source_url    = get_post_meta(get_the_ID(), 'source_url', true);
        $source_credit = get_post_meta(get_the_ID(), 'source_credit', true);
        if ($source_url) : ?>
          <p class="mt-6 text-xs text-secondary">
            Nguồn:
            <a href="<?php echo esc_url($source_url); ?>" rel="nofollow noopener" target="_blank" class="hover:text-brand">
              <?php echo esc_html($source_credit ?: $source_url); ?>
            </a>
          </p>
        <?php endif; ?>

        <?php get_template_part('template-parts/author-box'); ?>
      </div>
    </article>

    <?php get_template_part('template-parts/related-posts'); ?>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
