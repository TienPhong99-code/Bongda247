<?php
defined('ABSPATH') || exit;

/**
 * Sinh mục lục (TOC) từ các <h2> trong HTML nội dung.
 * Gắn id="muc-N" (N tuần tự) vào H2 chưa có id; H2 đã có id → giữ nguyên.
 * @return array ['items' => [ ['id'=>string,'text'=>string], ... ], 'content' => string]
 */
function bd_toc($html) {
    $items = [];
    $i = 0;
    $content = preg_replace_callback('/<h2\b([^>]*)>(.*?)<\/h2>/is', function ($m) use (&$items, &$i) {
        $attrs = $m[1];
        $inner = $m[2];
        $text  = trim(wp_strip_all_tags($inner));
        // H2 đã có id → giữ nguyên, dùng id sẵn có cho anchor.
        if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $idm)) {
            if ($text !== '') $items[] = ['id' => $idm[1], 'text' => $text];
            return $m[0];
        }
        $i++;
        $id = 'muc-' . $i;
        if ($text !== '') $items[] = ['id' => $id, 'text' => $text];
        return '<h2 id="' . $id . '"' . $attrs . '>' . $inner . '</h2>';
    }, $html);

    return ['items' => $items, 'content' => $content];
}
