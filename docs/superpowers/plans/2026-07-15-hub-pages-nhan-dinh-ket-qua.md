# SEO-SP1 Hub pages (Nhận định + Kết quả) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm 2 trang landing `/ket-qua-bong-da/` (kết quả FINISHED 5 giải, nhóm theo ngày) và `/nhan-dinh/` (kết hợp cards CPT match_insight sắp tới + bài phân tích tag `nhan-dinh`), kèm link nav.

**Architecture:** 1 hàm gom kết quả trong data layer (`bd_fd_results_by_date`), 2 page-template (`page-ket-qua.php`, `page-nhan-dinh.php`) bám pattern `page-lich-thi-dau.php`, 1 template-part card (`insight-card.php`) tách từ carousel, + WP Page/tag tạo qua wp-cli + link nav header.

**Tech Stack:** WordPress classic theme (PHP 8.2), Tailwind v4 (`npm run build:css`), football-data.org (data layer SP4). Test hàm PHP qua `./wp/bin/wp eval-file`; verify trực quan curl + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-14-hub-pages-nhan-dinh-ket-qua-design.md`

## Global Constraints

- Escape MỌI dữ liệu ngoài: `esc_url()` (crest, link), `esc_html()` (tên đội/giải/tỉ số/giờ/ngày).
- KHÔNG thêm API endpoint mới — chỉ `bd_fd_fixtures($code)` (đã cache 3h).
- Giờ/ngày theo timezone site: `wp_date($fmt, $ts)` (KHÔNG `date()`).
- Trang **Nhận định**: mỗi khối tự ẩn khi rỗng; cả hai rỗng → đoạn fallback. Trang **Kết quả**: rỗng → thông báo (KHÔNG ẩn trang).
- Bài phân tích gom bằng **tag `nhan-dinh`** (post_tag), KHÔNG dùng category.
- Nav link qua `home_url()`; bám class nav hiện có (BXH/Lịch).
- Page-template bám pattern `page-lich-thi-dau.php`: `get_header()` → `<div class="container"><h1 ...>` → `get_footer()`.

---

### Task 1: Hàm `bd_fd_results_by_date()` (data layer)

**Files:**
- Modify: `wp/themes/bongda247/inc/football-data.php` (thêm cuối file, sau `bd_fd_featured_matches`)
- Test (tạm, KHÔNG commit): `/tmp/bd_results_test.php` chạy qua `./wp/bin/wp eval-file`

**Interfaces:**
- Consumes: `BD_FD_LEAGUES`, `bd_fd_fixtures($code)` (đã có).
- Produces: `bd_fd_results_by_date($days = 7)` → mảng nhóm `[ ['label'=>string, 'date'=>'Y-m-d', 'matches'=>[...]], ... ]` sắp ngày giảm dần; mỗi match = 1 trận `bd_fd_fixtures()` + `'ts'` (int) + `'league_slug'` + `'league_name'`, trong nhóm sắp ts giảm dần. Task 2 dùng.

- [ ] **Step 1: Viết test tạm (thất bại vì hàm chưa có)**

Tạo `/tmp/bd_results_test.php`:
```php
<?php
$now = time();
$mk = function ($st,$d,$h,$a,$sh,$sa) use ($now) {
    return ['utcDate'=>gmdate('Y-m-d\TH:i:s\Z',$now+$d*DAY_IN_SECONDS),'status'=>$st,'home'=>$h,'homeCrest'=>'','away'=>$a,'awayCrest'=>'','sh'=>$sh,'sa'=>$sa];
};
$seed = [
  'PL'  => [$mk('FINISHED',0,'A','B',2,0), $mk('FINISHED',-1,'C','D',1,1), $mk('FINISHED',-2,'E','F',0,0),
            $mk('SCHEDULED',1,'G','H',null,null), $mk('FINISHED',-10,'OLD','X',3,3)],
  'PD'  => [$mk('FINISHED',-1,'I','J',4,2)],
  'BL1' => [], 'SA' => [], 'FL1' => [],
];
foreach ($seed as $c=>$arr){ update_option('bd_fd_fixtures_'.$c,$arr,false); set_transient('bd_fd_fresh_fixtures_'.$c,1,HOUR_IN_SECONDS); }

$g = bd_fd_results_by_date(7);
$fail = [];
$all = array_merge(...array_map(fn($x)=>$x['matches'], $g)) ?: [];
if (count($all) !== 4) $fail[] = 'tổng trận='.count($all).' (mong 4: loại SCHEDULED +1 và FINISHED -10)';
foreach ($all as $m) {
    if ($m['status'] !== 'FINISHED') { $fail[]='lọt trận không FINISHED'; break; }
    if ($m['ts'] < $now - 7*DAY_IN_SECONDS) { $fail[]='lọt trận quá 7 ngày'; break; }
    if (empty($m['league_slug']) || empty($m['league_name']) || empty($m['ts'])) { $fail[]='thiếu league/ts'; break; }
}
// nhóm sắp ngày giảm dần
for ($i=0; $i<count($g)-1; $i++) if ($g[$i]['date'] <= $g[$i+1]['date']) { $fail[]='nhóm chưa giảm dần theo ngày'; break; }
if (empty($g[0]['label'])) $fail[] = 'thiếu label nhóm';

foreach ($seed as $c=>$arr){ delete_option('bd_fd_fixtures_'.$c); delete_transient('bd_fd_fresh_fixtures_'.$c); }
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FATAL (hàm chưa có)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_results_test.php`
Expected: FATAL `Call to undefined function bd_fd_results_by_date()`.

- [ ] **Step 3: Thêm hàm vào `inc/football-data.php`**

Thêm cuối file:
```php
/**
 * Kết quả (FINISHED) 5 giải trong $days ngày gần nhất, nhóm theo ngày (giờ site) giảm dần.
 * Trả: [ ['label'=>'Hôm nay'|'Hôm qua'|'dd/mm', 'date'=>'Y-m-d', 'matches'=>[...]], ... ]
 * Mỗi match = 1 trận bd_fd_fixtures() + 'ts' (unix) + 'league_slug' + 'league_name'.
 * Tái dùng cache bd_fd_fixtures() — không thêm API.
 */
function bd_fd_results_by_date($days = 7) {
    $cutoff  = time() - $days * DAY_IN_SECONDS;
    $matches = [];
    foreach (BD_FD_LEAGUES as $slug => $lg) {
        foreach (bd_fd_fixtures($lg['code']) as $m) {
            if (($m['status'] ?? '') !== 'FINISHED') continue;
            if (empty($m['utcDate'])) continue;
            $ts = strtotime($m['utcDate']);
            if ($ts === false || $ts < $cutoff) continue;
            $m['ts']          = $ts;
            $m['league_slug'] = $slug;
            $m['league_name'] = $lg['name'];
            $matches[] = $m;
        }
    }
    if (!$matches) return [];

    $groups = [];
    foreach ($matches as $m) {
        $groups[wp_date('Y-m-d', $m['ts'])][] = $m;
    }
    krsort($groups); // ngày giảm dần (khóa ISO sort chuỗi = đúng thứ tự)

    $today     = wp_date('Y-m-d');
    $yesterday = wp_date('Y-m-d', time() - DAY_IN_SECONDS);
    $out = [];
    foreach ($groups as $day => $ms) {
        usort($ms, function ($a, $b) { return $b['ts'] <=> $a['ts']; }); // trong ngày: mới nhất trước
        if ($day === $today)          $label = 'Hôm nay';
        elseif ($day === $yesterday)  $label = 'Hôm qua';
        else                          $label = wp_date('d/m', $ms[0]['ts']);
        $out[] = ['label' => $label, 'date' => $day, 'matches' => $ms];
    }
    return $out;
}
```

- [ ] **Step 4: Chạy lại test — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_results_test.php`
Expected: `PASS`

- [ ] **Step 5: Xoá file test tạm**

Run: `rm -f /tmp/bd_results_test.php`

- [ ] **Step 6: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/inc/football-data.php
git commit -m "feat(theme): bd_fd_results_by_date — kết quả 5 giải nhóm theo ngày"
```

---

### Task 2: Trang `/ket-qua-bong-da/` (page template + WP Page + nav)

**Files:**
- Create: `wp/themes/bongda247/page-ket-qua.php`
- Modify: `wp/themes/bongda247/header.php` (thêm link "Kết quả" nav desktop + mobile)
- Build: `wp/themes/bongda247/dist/main.css`

**Interfaces:**
- Consumes: `bd_fd_results_by_date($days=7)` (Task 1).
- Produces: trang public tại slug `ket-qua-bong-da`.

- [ ] **Step 1: Tạo `page-ket-qua.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Kết quả bóng đá</h1>

  <?php $bd_groups = bd_fd_results_by_date(7); ?>
  <?php if (!$bd_groups) : ?>
    <p class="text-secondary">Chưa có kết quả trong 7 ngày qua.</p>
  <?php else : ?>
    <?php foreach ($bd_groups as $bd_g) : ?>
      <h2 class="font-hemi text-lg uppercase text-secondary mt-8 mb-3"><?php echo esc_html($bd_g['label']); ?></h2>
      <div class="rounded-2xl border border-card bg-card overflow-hidden">
        <?php foreach ($bd_g['matches'] as $bd_m) :
          $bd_link  = home_url('/lich-thi-dau/?league=' . $bd_m['league_slug']);
          $bd_score = ($bd_m['sh'] ?? '?') . '–' . ($bd_m['sa'] ?? '?');
        ?>
          <a href="<?php echo esc_url($bd_link); ?>" class="flex items-center gap-3 px-4 py-2.5 border-t border-card first:border-t-0 hover:bg-control transition-colors text-sm">
            <span class="w-10 shrink-0 text-secondary text-xs"><?php echo esc_html(wp_date('H:i', $bd_m['ts'])); ?></span>
            <span class="flex-1 flex items-center justify-end gap-2 min-w-0">
              <span class="truncate text-right"><?php echo esc_html($bd_m['home']); ?></span>
              <?php if (!empty($bd_m['homeCrest'])) : ?><img src="<?php echo esc_url($bd_m['homeCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
            </span>
            <span class="shrink-0 font-semibold px-1 tabular-nums"><?php echo esc_html($bd_score); ?></span>
            <span class="flex-1 flex items-center gap-2 min-w-0">
              <?php if (!empty($bd_m['awayCrest'])) : ?><img src="<?php echo esc_url($bd_m['awayCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
              <span class="truncate"><?php echo esc_html($bd_m['away']); ?></span>
            </span>
            <span class="hidden sm:block shrink-0 text-xs text-secondary w-24 text-right truncate"><?php echo esc_html($bd_m['league_name']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 2: Thêm link "Kết quả" vào nav `header.php`**

Ngay SAU dòng nav desktop của "Lịch" (dòng có `home_url('/lich-thi-dau/')` với class `text-sm font-medium ...`), thêm:
```php
            <li><a href="<?php echo esc_url(home_url('/ket-qua-bong-da/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">Kết quả</a></li>
```
Và ngay SAU dòng nav MOBILE của "Lịch" (class `block py-2 text-sm font-medium ...`), thêm:
```php
            <li><a href="<?php echo esc_url(home_url('/ket-qua-bong-da/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Kết quả</a></li>
```

- [ ] **Step 3: Tạo WP Page `ket-qua-bong-da`**

Run:
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp post create --post_type=page --post_status=publish --post_title="Kết quả bóng đá" --post_name="ket-qua-bong-da" --porcelain
```
Expected: in ra 1 ID (số). WordPress tự dùng `page-ket-qua.php` cho Page slug `ket-qua-bong-da` (template theo slug).

- [ ] **Step 4: Build CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: `Done in ...ms`.

- [ ] **Step 5: Verify off-season + có dữ liệu**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
curl -s "http://bongda247.local/ket-qua-bong-da/" -o /tmp/kq.html -w "HTTP %{http_code}\n"
grep -c "Kết quả bóng đá" /tmp/kq.html            # >=1 (heading)
grep -c "Chưa có kết quả" /tmp/kq.html            # 1 khi off-season
grep -ci "fatal error\|warning:\|notice:" /tmp/kq.html   # 0
```
Rồi prime cache mẫu qua `/tmp/bd_kq_seed.php`:
```php
<?php
$now=time();
$mk=fn($st,$d,$h,$a,$sh,$sa)=>['utcDate'=>gmdate('Y-m-d\TH:i:s\Z',$now+$d*DAY_IN_SECONDS),'status'=>$st,'home'=>$h,'homeCrest'=>'','away'=>$a,'awayCrest'=>'','sh'=>$sh,'sa'=>$sa];
$seed=['PL'=>[$mk('FINISHED',0,'Arsenal','Chelsea',2,0),$mk('FINISHED',-1,'Liverpool','Everton',3,1)],'PD'=>[$mk('FINISHED',-1,'Barcelona','Girona',4,2)],'BL1'=>[],'SA'=>[],'FL1'=>[]];
foreach($seed as $c=>$a){update_option('bd_fd_fixtures_'.$c,$a,false);set_transient('bd_fd_fresh_fixtures_'.$c,1,HOUR_IN_SECONDS);}
echo "seeded\n";
```
Run: `./wp/bin/wp eval-file /tmp/bd_kq_seed.php` → rồi `curl -s http://bongda247.local/ket-qua-bong-da/ -o /tmp/kq2.html`; kiểm tra:
```bash
grep -c "Hôm nay\|Hôm qua" /tmp/kq2.html          # >=1 (nhóm theo ngày)
grep -o "Arsenal\|Chelsea\|Liverpool\|Barcelona" /tmp/kq2.html | sort -u   # thấy các đội seed
grep -c "lich-thi-dau/?league=" /tmp/kq2.html     # = số trận (3)
```
Dọn: tạo `/tmp/bd_kq_unseed.php` (`<?php foreach(['PL','PD','BL1','SA','FL1'] as $c){delete_option('bd_fd_fixtures_'.$c);delete_transient('bd_fd_fresh_fixtures_'.$c);} echo "cleaned\n";`) → `./wp/bin/wp eval-file /tmp/bd_kq_unseed.php && rm -f /tmp/bd_kq_seed.php /tmp/bd_kq_unseed.php`.
Expected: off-season "Chưa có kết quả"; seeded → nhóm ngày + đội + 3 link; sau dọn về off-season. Nếu lệch, điều tra template — đừng sửa kỳ vọng.

- [ ] **Step 6: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/page-ket-qua.php wp/themes/bongda247/header.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): trang /ket-qua-bong-da/ (kết quả 5 giải nhóm theo ngày) + nav"
```

---

### Task 3: Trang `/nhan-dinh/` (insight-card + page template + WP Page + tag + nav)

**Files:**
- Create: `wp/themes/bongda247/template-parts/insight-card.php`
- Create: `wp/themes/bongda247/page-nhan-dinh.php`
- Modify: `wp/themes/bongda247/header.php` (thêm link "Nhận định" nav desktop + mobile)
- Build: `wp/themes/bongda247/dist/main.css`

**Interfaces:**
- Consumes: `bd_insights($n)` → `WP_Query` CPT match_insight (inc/query.php); `bd_insight_is_upcoming($match_time, $match_date)` → bool (inc/query.php).
- Produces: trang public tại slug `nhan-dinh`; template-part `insight-card.php` (render 1 card trong vòng lặp `the_post()`).

- [ ] **Step 1: Tạo `template-parts/insight-card.php`** (tách card từ carousel, không có wrapper swiper)

```php
<?php
defined('ABSPATH') || exit;
// 1 card nhận định — gọi TRONG vòng lặp the_post() của match_insight.
$bd_id    = get_the_ID();
$bd_flame = get_stylesheet_directory_uri() . '/assets/images/flame.png';
$bd_home  = (string) get_post_meta($bd_id, 'home_team', true);
$bd_away  = (string) get_post_meta($bd_id, 'away_team', true);
$bd_time  = (string) get_post_meta($bd_id, 'match_time', true);
$bd_hot   = (int) get_post_meta($bd_id, 'hot', true) === 1;
$bd_lines = (array) get_post_meta($bd_id, 'insights', true);
$bd_pred  = (string) get_post_meta($bd_id, 'prediction', true);
?>
<div class="group flex gap-4 rounded-lg bg-card p-3 border border-card flex-col h-full">
  <div>
    <div class="flex justify-between items-center mb-4">
      <span class="text-secondary text-sm font-medium"><?php echo esc_html($bd_time); ?></span>
      <?php if ($bd_hot) : ?><img src="<?php echo esc_url($bd_flame); ?>" alt="Hot" width="20" height="20"><?php endif; ?>
    </div>
    <div class="mb-4">
      <h3 class="font-hemi text-xl flex items-center gap-3"><?php echo esc_html($bd_home); ?> <span>VS</span> <?php echo esc_html($bd_away); ?></h3>
    </div>
    <?php if ($bd_lines) : ?>
      <ul class="space-y-4 mb-2">
        <?php foreach ($bd_lines as $bd_line) : ?>
          <li class="text-sm text-secondary flex items-start gap-3 leading-relaxed">
            <span class="w-1.5 h-1.5 rounded-full bg-brand mt-1.5 shrink-0"></span>
            <?php echo esc_html($bd_line); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php if ($bd_pred) : ?>
    <div class="inline-block mt-auto w-fit ml-auto text-sm transition-all p-2 px-4 rounded-full font-hemi bg-prediction"><?php echo esc_html($bd_pred); ?></div>
  <?php endif; ?>
</div>
```

- [ ] **Step 2: Tạo `page-nhan-dinh.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Nhận định bóng đá</h1>

  <?php
  // Khối trên: nhận định trận sắp tới (CPT), lọc hạn như carousel trang chủ.
  $bd_q = bd_insights(8);
  ob_start();
  $bd_shown = 0;
  while ($bd_q->have_posts() && $bd_shown < 8) : $bd_q->the_post();
      $bd_mt = (string) get_post_meta(get_the_ID(), 'match_time', true);
      $bd_md = (string) get_post_meta(get_the_ID(), 'match_date', true);
      if (!bd_insight_is_upcoming($bd_mt, $bd_md)) continue;
      $bd_shown++;
      get_template_part('template-parts/insight-card');
  endwhile;
  wp_reset_postdata();
  $bd_cards = ob_get_clean();
  ?>
  <?php if ($bd_shown) : ?>
    <h2 class="font-hemi text-lg uppercase text-secondary mb-3">Nhận định trận sắp tới</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10"><?php echo $bd_cards; ?></div>
  <?php endif; ?>

  <?php
  // Khối dưới: bài phân tích đầy đủ (tag nhan-dinh).
  $bd_articles = new WP_Query([
      'tag'                 => 'nhan-dinh',
      'posts_per_page'      => 8,
      'ignore_sticky_posts' => true,
      'no_found_rows'       => true,
  ]);
  ?>
  <?php if ($bd_articles->post_count) : ?>
    <h2 class="font-hemi text-lg uppercase text-secondary mb-3">Bài phân tích</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php while ($bd_articles->have_posts()) : $bd_articles->the_post(); ?>
        <a href="<?php echo esc_url(get_permalink()); ?>" class="block group">
          <?php if (has_post_thumbnail()) : ?>
            <div class="rounded-2xl overflow-hidden border border-card mb-3 aspect-video">
              <?php the_post_thumbnail('bd_hero', ['class' => 'w-full h-full object-cover transition-transform group-hover:scale-105', 'alt' => the_title_attribute(['echo' => false])]); ?>
            </div>
          <?php endif; ?>
          <h3 class="font-oswald text-lg font-bold leading-snug group-hover:text-brand transition-colors line-clamp-2"><?php the_title(); ?></h3>
          <time class="text-xs text-secondary mt-1 block"><?php echo esc_html(get_the_date('d/m/Y')); ?></time>
        </a>
      <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
  <?php endif; ?>

  <?php if (!$bd_shown && !$bd_articles->post_count) : ?>
    <p class="text-secondary">Chưa có nhận định — quay lại sau khi có trận sắp diễn ra.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 3: Thêm link "Nhận định" vào nav `header.php`**

Ngay SAU link "Kết quả" desktop (Task 2 đã thêm), thêm:
```php
            <li><a href="<?php echo esc_url(home_url('/nhan-dinh/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">Nhận định</a></li>
```
Và sau link "Kết quả" mobile:
```php
            <li><a href="<?php echo esc_url(home_url('/nhan-dinh/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Nhận định</a></li>
```

- [ ] **Step 4: Tạo WP Page `nhan-dinh` + tag `nhan-dinh`**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp post create --post_type=page --post_status=publish --post_title="Nhận định bóng đá" --post_name="nhan-dinh" --porcelain
./wp/bin/wp term create post_tag "Nhận định" --slug=nhan-dinh --porcelain
```
Expected: mỗi lệnh in 1 ID. (Nếu tag đã tồn tại → wp báo lỗi "already exists" — bỏ qua, không sao.)

- [ ] **Step 5: Build CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: `Done in ...ms`.

- [ ] **Step 6: Verify — rỗng + có dữ liệu**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
curl -s "http://bongda247.local/nhan-dinh/" -o /tmp/nd.html -w "HTTP %{http_code}\n"
grep -c "Nhận định bóng đá" /tmp/nd.html          # >=1 (heading)
grep -c "Chưa có nhận định" /tmp/nd.html          # 1 khi cả 2 khối rỗng
grep -ci "fatal error\|warning:\|notice:" /tmp/nd.html   # 0
```
Seed 1 match_insight (tương lai) + 1 post gắn tag `nhan-dinh`:
```bash
FUT=$(date -u -v+2d '+%Y-%m-%dT%H:%M:%SZ' 2>/dev/null || date -u -d '+2 days' '+%Y-%m-%dT%H:%M:%SZ')
MI=$(./wp/bin/wp post create --post_type=match_insight --post_status=publish --post_title="Arsenal vs Chelsea" --porcelain)
./wp/bin/wp post meta update "$MI" home_team "Arsenal"; ./wp/bin/wp post meta update "$MI" away_team "Chelsea"
./wp/bin/wp post meta update "$MI" match_time "21:00 - 20/07"; ./wp/bin/wp post meta update "$MI" match_date "$FUT"
./wp/bin/wp post meta update "$MI" prediction "Arsenal thắng 2-1"
PA=$(./wp/bin/wp post create --post_type=post --post_status=publish --post_title="Nhận định Arsenal vs Chelsea vòng 5" --post_content="Phân tích demo." --porcelain)
./wp/bin/wp post term add "$PA" post_tag nhan-dinh --by=slug   # gán tag theo SLUG (khớp WP_Query tag=nhan-dinh)
curl -s "http://bongda247.local/nhan-dinh/" -o /tmp/nd2.html
grep -c "Nhận định trận sắp tới" /tmp/nd2.html     # 1 (khối trên hiện)
grep -c "Arsenal thắng 2-1" /tmp/nd2.html          # 1 (prediction card)
grep -c "Bài phân tích" /tmp/nd2.html              # 1 (khối dưới hiện)
grep -c "vòng 5" /tmp/nd2.html                     # 1 (bài tag)
# Dọn seed
./wp/bin/wp post delete "$MI" "$PA" --force
```
Expected: rỗng → "Chưa có nhận định"; seeded → cả 2 khối + card + bài; sau dọn về rỗng. Nếu lệch, điều tra template.

- [ ] **Step 7: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/template-parts/insight-card.php wp/themes/bongda247/page-nhan-dinh.php wp/themes/bongda247/header.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): trang /nhan-dinh/ (cards CPT sắp tới + bài phân tích tag) + nav"
```

---

## Sau khi xong 3 task

- Cập nhật CLAUDE.md (routes `/nhan-dinh/`, `/ket-qua-bong-da/`; cây thư mục thêm `page-nhan-dinh.php`, `page-ket-qua.php`, `insight-card.php`; `bd_fd_results_by_date`).
- Ledger `.superpowers/sdd/hub-pages/progress.md`.
- Follow-up (không thuộc SP): bot Luồng 6 gắn tag `nhan-dinh` khi tạo bài nhận định (`web/bot-press.js` + `lib/wp.js`).
- Finishing gate (bot `npm test` 9/9; curl 2 trang 200) → merge + push.
