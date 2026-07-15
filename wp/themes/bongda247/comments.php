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
      'class_submit'         => 'rounded-lg bg-brand text-white px-5 py-2 text-sm font-medium hover:opacity-90 transition-opacity cursor-pointer',
      'label_submit'         => 'Gửi bình luận',
    ]);
  else : ?>
    <p class="text-secondary text-sm rounded-2xl border border-card bg-card p-4">Vui lòng <a href="<?php echo esc_url(home_url('/tai-khoan/')); ?>" class="text-brand hover:underline">đăng nhập</a> để bình luận và nhận điểm.</p>
  <?php endif; ?>
</section>
