<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <?php while (have_posts()) : the_post();
      $cats = get_the_category();
      $cat  = $cats[0] ?? null;
      $tags = get_the_tags();
  ?>
    <div class="row">
      <div class="col col-8">
    <article>
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

        <?php if (is_user_logged_in()) :
          $bd_pid   = get_the_ID();
          $bd_liked = in_array($bd_pid, (array) get_user_meta(get_current_user_id(), 'bd_liked_posts', true), true);
        ?>
          <div data-bd-points data-bd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-bd-nonce="<?php echo esc_attr(wp_create_nonce('bd_points')); ?>" data-bd-post="<?php echo esc_attr($bd_pid); ?>" class="mt-8 flex items-center gap-3 flex-wrap">
            <button data-bd-like type="button" aria-pressed="<?php echo $bd_liked ? 'true' : 'false'; ?>" class="flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors <?php echo $bd_liked ? 'text-brand border-brand' : 'text-secondary border-card hover:border-brand'; ?>">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.9-9.6-9.2C1 8.5 2.8 5.5 5.9 5.5c1.9 0 3.3 1 4.1 2.3C10.8 6.5 12.2 5.5 14.1 5.5c3.1 0 4.9 3 3.5 6.3C19.5 16.1 12 21 12 21z"/></svg>
              <span data-bd-like-count><?php echo (int) get_post_meta($bd_pid, 'bd_like_count', true); ?></span>
            </button>
            <span class="text-xs text-secondary">Thích để +1 điểm</span>
            <div class="flex items-center gap-2" data-bd-share-url="<?php echo esc_url(get_permalink()); ?>" data-bd-share-title="<?php echo esc_attr(get_the_title()); ?>">
              <span class="text-xs text-secondary">Chia sẻ +3đ:</span>
              <button data-bd-share="fb" type="button" aria-label="Chia sẻ Facebook" class="p-2 rounded-full border border-card text-secondary hover:text-brand hover:border-brand transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3l.5-3H14V4.5c0-.8.3-1.5 1.5-1.5H17V.2C16.7.1 15.7 0 14.6 0 12.2 0 10.5 1.5 10.5 4.2V6H8v3h2.5v9h3.5V9z"/></svg>
              </button>
              <button data-bd-share="x" type="button" aria-label="Chia sẻ X" class="p-2 rounded-full border border-card text-secondary hover:text-brand hover:border-brand transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 1.2h3.5l-7.6 8.7 8.9 11.8h-7l-5.5-7.2-6.3 7.2H1.4l8.1-9.3L1 1.2h7.2l5 6.6 5.7-6.6zm-1.2 18.4h1.9L6.9 3.2H4.8l12.9 16.4z"/></svg>
              </button>
              <button data-bd-share="copy" type="button" aria-label="Copy link" class="p-2 rounded-full border border-card text-secondary hover:text-brand hover:border-brand transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg>
              </button>
            </div>
          </div>
        <?php else : ?>
          <div class="mt-8">
            <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="inline-flex items-center gap-2 rounded-full border border-card px-4 py-2 text-sm text-secondary hover:border-brand transition-colors">♥ Thích (đăng nhập để tích điểm)</a>
          </div>
        <?php endif; ?>
      </div>
    </article>

        <?php comments_template(); ?>
      </div>

      <div class="col col-4">
        <aside class="lg:sticky lg:top-24 space-y-6">
          <?php get_template_part('template-parts/single-sidebar'); ?>
        </aside>
      </div>
    </div>

    <?php get_template_part('template-parts/related-posts'); ?>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
