# Dải trận đấu nổi bật (đầu trang chủ) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm dải "Trận đấu nổi bật" ở đầu trang chủ — gộp kết quả vừa đá + trận sắp tới của 5 giải VĐQG, danh sách dòng ngang gọn, ẩn hẳn khi không có trận.

**Architecture:** Thêm 1 hàm thuần `bd_fd_featured_matches()` trong data layer `inc/football-data.php` (tái dùng `bd_fd_fixtures()` đã cache — không thêm API endpoint), 1 template-part `featured-matches.php` tự bọc `<section>` và tự ẩn khi rỗng, và 1 dòng `get_template_part` ở đầu `front-page.php`.

**Tech Stack:** WordPress classic theme (PHP 8.2), Tailwind CSS v4 (build qua `npm run build:css`), football-data.org API (đã có data layer SP4). Test hàm PHP qua `./wp/bin/wp eval-file`; verify trực quan qua curl + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-14-home-featured-matches-design.md`

## Global Constraints

- Escape MỌI dữ liệu ngoài: `esc_url()` cho crest + link, `esc_html()` cho tên đội/giải/tỉ số/giờ.
- KHÔNG thêm API endpoint mới — chỉ gọi `bd_fd_fixtures($code)` (đã cache 3h stale-while-revalidate).
- Tối đa **8 dòng**, cân bằng vừa-đá/sắp-tới, **sắp tăng dần theo thời gian** để hiển thị (quá khứ → tương lai).
- `upcoming` = `status ∈ {SCHEDULED, TIMED}`; `results` = `status === 'FINISHED'`; `IN_PLAY`/`PAUSED`/khác → bỏ qua.
- Rỗng (0 trận / thiếu key / API lỗi) → **ẩn hẳn** (template `return;` trước khi in `<section>`).
- Mỗi dòng = link tới `/lich-thi-dau/?league={league_slug}`. Heading "Trận đấu nổi bật". Vị trí: đầu `front-page.php`, trên section "Tin mới cập nhật".
- Giờ hiển thị theo timezone site: dùng `wp_date($format, $ts)` với `$ts` = unix timestamp (KHÔNG dùng `date()`).
- Font/heading bám theme: `font-hemi text-2xl uppercase border-l-4 border-brand pl-4`; card `bg-card border border-card rounded-2xl`; `text-secondary`/`text-brand`.

---

### Task 1: Hàm gộp `bd_fd_featured_matches()` (data layer)

**Files:**
- Modify: `wp/themes/bongda247/inc/football-data.php` (thêm hàm ở cuối file, sau `bd_fd_fixtures`)
- Test (tạm, KHÔNG commit): `/tmp/bd_ftest.php` chạy qua `./wp/bin/wp eval-file`

**Interfaces:**
- Consumes: `BD_FD_LEAGUES` (map slug→`{code,name}`), `bd_fd_fixtures($code)` → mảng trận `{utcDate,status,home,homeCrest,away,awayCrest,sh,sa}` (đã có trong file).
- Produces: `bd_fd_featured_matches($limit = 8)` → mảng ≤ `$limit` trận; mỗi phần tử = 1 trận của `bd_fd_fixtures()` **cộng thêm** khóa `'ts'` (int unix), `'league_slug'` (string), `'league_name'` (string); đã sắp **tăng dần theo `ts`**. Template Task 2 dựa vào các khóa này.

- [ ] **Step 1: Viết test script tạm (thất bại vì hàm chưa có)**

Tạo `/tmp/bd_ftest.php`:

```php
<?php
// Seed fixtures cache cho 5 giải (không gọi API), kiểm tra bd_fd_featured_matches().
$now = time();
$mk = function ($status, $day_offset, $home, $away, $sh, $sa) use ($now) {
    return [
        'utcDate'   => gmdate('Y-m-d\TH:i:s\Z', $now + $day_offset * DAY_IN_SECONDS),
        'status'    => $status,
        'home'      => $home, 'homeCrest' => 'https://x/'.$home.'.png',
        'away'      => $away, 'awayCrest' => 'https://x/'.$away.'.png',
        'sh'        => $sh, 'sa' => $sa,
    ];
};
// PL: 5 trận đã đá (now-1..-5). PD: 5 trận sắp tới (now+1..+5). 3 giải còn lại rỗng.
$pl = [];
for ($i = 1; $i <= 5; $i++) $pl[] = $mk('FINISHED', -$i, "PLh$i", "PLa$i", 2, $i);
$pd = [];
for ($i = 1; $i <= 5; $i++) $pd[] = $mk('SCHEDULED', $i, "PDh$i", "PDa$i", null, null);
$seed = ['PL' => $pl, 'PD' => $pd, 'BL1' => [], 'SA' => [], 'FL1' => []];
foreach ($seed as $code => $arr) {
    update_option('bd_fd_fixtures_' . $code, $arr, false);
    set_transient('bd_fd_fresh_fixtures_' . $code, 1, HOUR_IN_SECONDS);
}

$out = bd_fd_featured_matches(8);
$fail = [];
if (count($out) !== 8) $fail[] = 'count=' . count($out) . ' (mong 8)';
$fin = array_filter($out, fn($m) => $m['status'] === 'FINISHED');
$up  = array_filter($out, fn($m) => $m['status'] === 'SCHEDULED');
if (count($fin) !== 4) $fail[] = 'finished=' . count($fin) . ' (mong 4)';
if (count($up) !== 4)  $fail[] = 'upcoming=' . count($up) . ' (mong 4)';
$ts = array_column($out, 'ts');
$sorted = $ts; sort($sorted);
if ($ts !== $sorted) $fail[] = 'chưa sắp tăng dần theo ts';
foreach ($out as $m) {
    if (empty($m['league_slug']) || empty($m['league_name'])) { $fail[] = 'thiếu league_slug/name'; break; }
}
// Trận đã đá phải là 4 trận gần now nhất (now-1..-4), KHÔNG lấy now-5.
foreach ($out as $m) {
    if ($m['status'] === 'FINISHED' && $m['ts'] <= $now - 5 * DAY_IN_SECONDS) { $fail[] = 'lấy nhầm trận finished quá cũ (now-5)'; break; }
}

// Cleanup
foreach ($seed as $code => $arr) {
    delete_option('bd_fd_fixtures_' . $code);
    delete_transient('bd_fd_fresh_fixtures_' . $code);
}

echo $fail ? ('FAIL: ' . implode(' | ', $fail) . "\n") : "PASS\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng lỗi vì hàm chưa định nghĩa**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_ftest.php`
Expected: FATAL — `Call to undefined function bd_fd_featured_matches()` (hoặc `Error: ... undefined`).

- [ ] **Step 3: Thêm hàm vào `inc/football-data.php`**

Thêm vào cuối file (sau hàm `bd_fd_fixtures`, trước dòng cuối):

```php
/**
 * Gộp trận nổi bật 5 giải: tối đa $limit trận cân bằng quanh now
 * (vừa đá + sắp tới), sắp tăng dần theo utcDate để hiển thị.
 * Mỗi phần tử = 1 trận của bd_fd_fixtures() + 'ts' (unix) + 'league_slug' + 'league_name'.
 * Tái dùng cache bd_fd_fixtures() — không thêm API endpoint.
 */
function bd_fd_featured_matches($limit = 8) {
    $finished = [];
    $upcoming = [];
    foreach (BD_FD_LEAGUES as $slug => $lg) {
        foreach (bd_fd_fixtures($lg['code']) as $m) {
            if (empty($m['utcDate'])) continue;
            $ts = strtotime($m['utcDate']);
            if ($ts === false) continue;
            $m['ts']          = $ts;
            $m['league_slug'] = $slug;
            $m['league_name'] = $lg['name'];
            if ($m['status'] === 'FINISHED') {
                $finished[] = $m;
            } elseif ($m['status'] === 'SCHEDULED' || $m['status'] === 'TIMED') {
                $upcoming[] = $m;
            }
            // IN_PLAY / PAUSED / khác → bỏ qua (live ngoài phạm vi)
        }
    }

    // finished: gần now nhất trước (giảm dần theo ts); upcoming: gần now nhất trước (tăng dần)
    usort($finished, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
    usort($upcoming, function ($a, $b) { return $a['ts'] <=> $b['ts']; });

    $taken_finished = array_slice($finished, 0, intdiv($limit, 2));
    $taken_upcoming = array_slice($upcoming, 0, $limit - count($taken_finished));
    // upcoming ít → lấp thêm finished tới $limit
    if (count($taken_finished) + count($taken_upcoming) < $limit) {
        $taken_finished = array_slice($finished, 0, $limit - count($taken_upcoming));
    }

    $out = array_merge($taken_finished, $taken_upcoming);
    usort($out, function ($a, $b) { return $a['ts'] <=> $b['ts']; }); // hiển thị: quá khứ → tương lai
    return $out;
}
```

- [ ] **Step 4: Chạy lại test — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_ftest.php`
Expected: `PASS`

- [ ] **Step 5: Xoá file test tạm (không commit)**

Run: `rm -f /tmp/bd_ftest.php`

- [ ] **Step 6: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/inc/football-data.php
git commit -m "feat(theme): bd_fd_featured_matches — gộp trận nổi bật 5 giải cho trang chủ"
```

---

### Task 2: Template-part `featured-matches.php` + tích hợp front-page

**Files:**
- Create: `wp/themes/bongda247/template-parts/featured-matches.php`
- Modify: `wp/themes/bongda247/front-page.php:1-2` (thêm 1 dòng sau `get_header()`)
- Build: `wp/themes/bongda247/dist/main.css` (qua `npm run build:css`)

**Interfaces:**
- Consumes: `bd_fd_featured_matches($limit = 8)` (Task 1) → mảng trận với khóa `utcDate,status,home,homeCrest,away,awayCrest,sh,sa,ts,league_slug,league_name`.
- Produces: HTML section dải trận (hoặc không in gì nếu rỗng). Không có hàm cho task sau.

- [ ] **Step 1: Tạo template-part `featured-matches.php`**

Tạo `wp/themes/bongda247/template-parts/featured-matches.php`:

```php
<?php
defined('ABSPATH') || exit;

$bd_matches = bd_fd_featured_matches(8);
if (empty($bd_matches)) return; // rỗng → ẩn hẳn, không in <section>

$bd_today = wp_date('Y-m-d'); // hôm nay theo giờ site
?>
<section>
  <div class="container">
    <div class="flex items-center justify-between mb-6">
      <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4">Trận đấu nổi bật</h2>
      <a href="<?php echo esc_url(home_url('/lich-thi-dau/')); ?>" class="text-sm text-secondary hover:text-brand whitespace-nowrap ml-4">Xem lịch đầy đủ →</a>
    </div>
    <div class="rounded-2xl border border-card bg-card overflow-hidden">
      <?php foreach ($bd_matches as $bd_m) :
        $bd_finished = ($bd_m['status'] === 'FINISHED');
        $bd_time  = wp_date('H:i', $bd_m['ts']);
        $bd_day   = (wp_date('Y-m-d', $bd_m['ts']) !== $bd_today) ? wp_date('d/m', $bd_m['ts']) : '';
        $bd_score = ($bd_m['sh'] ?? '?') . '–' . ($bd_m['sa'] ?? '?');
        $bd_link  = home_url('/lich-thi-dau/?league=' . $bd_m['league_slug']);
      ?>
        <a href="<?php echo esc_url($bd_link); ?>" class="flex items-center gap-3 px-4 py-2.5 border-t border-card first:border-t-0 hover:bg-control transition-colors text-sm">
          <span class="w-12 shrink-0 <?php echo $bd_finished ? 'text-secondary' : 'text-brand font-semibold'; ?>"><?php echo $bd_finished ? 'FT' : esc_html($bd_time); ?></span>
          <span class="flex-1 flex items-center justify-end gap-2 min-w-0">
            <span class="truncate text-right"><?php echo esc_html($bd_m['home']); ?></span>
            <?php if (!empty($bd_m['homeCrest'])) : ?><img src="<?php echo esc_url($bd_m['homeCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
          </span>
          <span class="shrink-0 font-semibold px-1 tabular-nums"><?php echo $bd_finished ? esc_html($bd_score) : '–'; ?></span>
          <span class="flex-1 flex items-center gap-2 min-w-0">
            <?php if (!empty($bd_m['awayCrest'])) : ?><img src="<?php echo esc_url($bd_m['awayCrest']); ?>" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy"><?php endif; ?>
            <span class="truncate"><?php echo esc_html($bd_m['away']); ?></span>
          </span>
          <span class="hidden sm:flex shrink-0 items-center gap-2 text-xs text-secondary w-28 justify-end">
            <span class="truncate"><?php echo esc_html($bd_m['league_name']); ?></span>
            <?php if ($bd_day) : ?><span class="whitespace-nowrap"><?php echo esc_html($bd_day); ?></span><?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 2: Thêm dòng vào `front-page.php`**

Sửa `wp/themes/bongda247/front-page.php` — chèn ngay sau dòng `get_header()` (dòng 1), TRƯỚC `<section>` đầu tiên:

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<?php get_template_part('template-parts/featured-matches'); ?>

<section>
  <div class="container">
    <div class="row">
      <div class="col col-8">
        <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Tin mới cập nhật</h2>
```

- [ ] **Step 3: Build CSS (gom class mới của dải)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: `Done in ...ms` (không lỗi).

- [ ] **Step 4: Verify off-season — dải PHẢI ẩn (cache fixtures hiện đang rỗng)**

Run:
```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
curl -s http://bongda247.local/ -o /tmp/bd_home.html -w "HTTP %{http_code}\n"
grep -c "Trận đấu nổi bật" /tmp/bd_home.html   # kỳ vọng 0 (ẩn)
grep -ci "fatal error\|warning:\|notice:" /tmp/bd_home.html  # kỳ vọng 0
```
Expected: HTTP 200; `Trận đấu nổi bật` = 0 (off-season → ẩn); lỗi PHP = 0.

- [ ] **Step 5: Verify có dữ liệu — prime cache mẫu rồi kiểm tra render**

Tạo `/tmp/bd_seed.php` (seed 3 giải: PL 3 trận đã đá, PD 3 trận sắp tới, SA 2 trận đã đá) và nạp:
```php
<?php
$now = time();
$mk = function ($st,$d,$h,$a,$sh,$sa) use ($now){ return ['utcDate'=>gmdate('Y-m-d\TH:i:s\Z',$now+$d*DAY_IN_SECONDS),'status'=>$st,'home'=>$h,'homeCrest'=>'','away'=>$a,'awayCrest'=>'','sh'=>$sh,'sa'=>$sa]; };
$seed = [
  'PL'  => [$mk('FINISHED',-1,'Arsenal','Chelsea',2,0),$mk('FINISHED',-2,'Liverpool','Everton',3,1),$mk('SCHEDULED',2,'Man City','Spurs',null,null)],
  'PD'  => [$mk('SCHEDULED',1,'Barcelona','Real Madrid',null,null),$mk('SCHEDULED',3,'Sevilla','Betis',null,null)],
  'SA'  => [$mk('FINISHED',-1,'Inter','Milan',1,1)],
  'BL1' => [], 'FL1' => [],
];
foreach ($seed as $c=>$arr){ update_option('bd_fd_fixtures_'.$c,$arr,false); set_transient('bd_fd_fresh_fixtures_'.$c,1,HOUR_IN_SECONDS); }
echo "seeded\n";
```
Run: `./wp/bin/wp eval-file /tmp/bd_seed.php`

Sau đó dùng Playwright (controller-side) mở `http://bongda247.local/`, xác nhận:
- Dải "Trận đấu nổi bật" xuất hiện ở **đầu trang** (trên "Tin mới cập nhật").
- Trận đã đá hiện `FT` + tỉ số (VD `Arsenal 2–0 Chelsea`); trận sắp tới hiện giờ `HH:mm` + `–`.
- Thứ tự **tăng dần theo thời gian**; ≤ 8 dòng.
- Mỗi dòng link `/lich-thi-dau/?league=...` đúng slug.
Chụp màn hình dải.

- [ ] **Step 6: Dọn cache mẫu (trả về off-season)**

Tạo `/tmp/bd_unseed.php`:
```php
<?php
foreach (['PL','PD','SA','BL1','FL1'] as $c){ delete_option('bd_fd_fixtures_'.$c); delete_transient('bd_fd_fresh_fixtures_'.$c); }
echo "cleaned\n";
```
Run: `./wp/bin/wp eval-file /tmp/bd_unseed.php && rm -f /tmp/bd_seed.php /tmp/bd_unseed.php`
Kiểm tra lại: `curl -s http://bongda247.local/ | grep -c "Trận đấu nổi bật"` → 0 (ẩn lại).

- [ ] **Step 7: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/template-parts/featured-matches.php wp/themes/bongda247/front-page.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): dải trận đấu nổi bật đầu trang (lịch+KQ 5 giải, ẩn khi rỗng)"
```

---

## Sau khi xong 2 task

- Cập nhật CLAUDE.md (cây thư mục theme thêm `featured-matches.php`; mục trang chủ nêu dải trận đấu nổi bật) + `inc/football-data.php` nêu hàm `bd_fd_featured_matches`.
- Ghi ledger `.superpowers/sdd/home-featured-matches/progress.md`.
- Chạy finishing gate (bot `npm test` vẫn 9/9 — không đụng bot; curl home 200) rồi hỏi phương án merge/push.
