<?php
defined('ABSPATH') || exit;
if (post_password_required()) {
    return;
}

if (!function_exists('bd_comment_render')) {
    function bd_comment_render($comment, $args, $depth) {
        ?>
        <li <?php comment_class('rounded-2xl border border-card bg-card p-4 list-none'); ?> id="comment-<?php comment_ID(); ?>">
          <div class="flex items-center gap-3 mb-2">
            <?php echo get_avatar($comment, 40, '', '', ['class' => 'rounded-full shrink-0']); ?>
            <div>
              <div class="font-semibold text-sm"><?php echo esc_html(get_comment_author()); ?></div>
              <time class="text-xs text-secondary"><?php echo esc_html(get_comment_date('d/m/Y')); ?></time>
            </div>
          </div>
          <div class="text-sm text-secondary leading-relaxed"><?php comment_text(); ?></div>
          <?php
          $bd_cid    = (int) $comment->comment_ID;
          $bd_clikes = (int) get_comment_meta($bd_cid, 'bd_comment_likes', true);
          ?>
          <div class="mt-3 flex items-center gap-4 text-xs">
            <?php if (is_user_logged_in()) :
                $bd_cliked = in_array($bd_cid, array_filter((array) get_user_meta(get_current_user_id(), 'bd_liked_comments', true)), true);
            ?>
              <button type="button" data-bd-comment-like data-bd-cid="<?php echo esc_attr($bd_cid); ?>" data-bd-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-bd-nonce="<?php echo esc_attr(wp_create_nonce('bd_points')); ?>" aria-pressed="<?php echo $bd_cliked ? 'true' : 'false'; ?>" class="inline-flex items-center gap-1 transition-colors <?php echo $bd_cliked ? 'text-brand' : 'text-secondary hover:text-brand'; ?>">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.9-9.6-9.2C1 8.5 2.8 5.5 5.9 5.5c1.9 0 3.3 1 4.1 2.3C10.8 6.5 12.2 5.5 14.1 5.5c3.1 0 4.9 3 3.5 6.3C19.5 16.1 12 21 12 21z"/></svg>
                <span data-bd-comment-like-count><?php echo $bd_clikes; ?></span>
              </button>
              <?php comment_reply_link(array_merge($args, [
                  'reply_text' => '↩ Trả lời',
                  'depth'      => $depth,
                  'max_depth'  => $args['max_depth'],
              ]), $comment); ?>
            <?php elseif ($bd_clikes > 0) : ?>
              <span class="inline-flex items-center gap-1 text-secondary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.9-9.6-9.2C1 8.5 2.8 5.5 5.9 5.5c1.9 0 3.3 1 4.1 2.3C10.8 6.5 12.2 5.5 14.1 5.5c3.1 0 4.9 3 3.5 6.3C19.5 16.1 12 21 12 21z"/></svg>
                <?php echo $bd_clikes; ?>
              </span>
            <?php endif; ?>
          </div>
        <?php // wp_list_comments tự đóng </li>
    }
}
?>
<section id="comments" class="max-w-4xl mx-auto mt-12">
  <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Bình luận<?php if (get_comments_number()) : ?> (<?php echo (int) get_comments_number(); ?>)<?php endif; ?></h2>

  <?php if (have_comments()) : ?>
    <ol class="space-y-4 mb-8">
      <?php wp_list_comments(['style' => 'ol', 'avatar_size' => 40, 'callback' => 'bd_comment_render']); ?>
    </ol>
    <?php the_comments_pagination(['mid_size' => 1]); ?>
  <?php endif; ?>

  <?php if (is_user_logged_in()) :
    comment_form([
      'title_reply'          => 'Để lại bình luận',
      'title_reply_before'   => '<h3 class="font-hemi text-lg uppercase mb-3">',
      'title_reply_after'    => '</h3>',
      'logged_in_as'         => '',
      'comment_notes_before' => '',
      'comment_notes_after'  => '',
      'comment_field'        => '<p class="mb-3"><textarea id="comment" name="comment" rows="4" required class="w-full rounded-lg bg-control border border-card px-3 py-2 text-sm focus:outline-none focus:border-brand" placeholder="Viết bình luận (+5 điểm cho bình luận đầu tiên của bạn)..."></textarea></p>',
      'class_submit'         => 'rounded-lg bg-brand text-on-brand px-5 py-2 text-sm font-medium hover:opacity-90 transition-opacity cursor-pointer',
      'label_submit'         => 'Gửi bình luận',
    ]);
  else : ?>
    <p class="text-secondary text-sm rounded-2xl border border-card bg-card p-4">Vui lòng <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="text-brand hover:underline">đăng nhập</a> để bình luận và nhận điểm.</p>
  <?php endif; ?>
</section>
