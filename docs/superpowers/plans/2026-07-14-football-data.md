# Dữ liệu bóng đá (BXH + Lịch) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hiển thị BXH + lịch thi đấu của 5 giải VĐQG trên archive giải + 2 trang riêng, dữ liệu football-data.org do WP tự fetch + cache.

**Architecture:** Lớp dữ liệu `inc/football-data.php` (fetch qua `wp_remote_get` + cache stale-while-revalidate: option bền + transient tươi). 2 template-part render (bảng BXH, lịch). `archive.php` nhúng cho category giải; 2 page template `/bang-xep-hang`, `/lich-thi-dau` với tab giải. Nav thêm BXH/Lịch.

**Tech Stack:** WordPress classic theme (PHP), WP HTTP API + Options/Transients cache, Tailwind v4 CLI, wp-cli `wp/bin/wp`.

> **PREREQUISITE (controller đã set trước khi chạy Task 1):** hằng `FOOTBALL_DATA_KEY` được `define()` trong `wp-config.php` của Local (`/Users/hotienphong/Local Sites/bongda247/app/public/wp-config.php`), giá trị lấy từ `web/.env` (`PUBLIC_FOOTBALL_DATA_KEY`). Không có key → feature trả rỗng (không fatal) nhưng test API sẽ không có data.

## Global Constraints

- **Site local:** `http://bongda247.local` (http, KHÔNG https).
- **API:** football-data.org v4, header `X-Auth-Token: FOOTBALL_DATA_KEY`. **10 req/phút.** Endpoint `/competitions/{code}/standings` + `/competitions/{code}/matches`.
- **5 giải:** map slug→code: `ngoai-hang-anh→PL`, `la-liga→PD`, `bundesliga→BL1`, `serie-a→SA`, `ligue-1→FL1`.
- **Cache:** BXH TTL 6h, lịch TTL 3h. stale-while-revalidate; hỏng API → phục vụ data cũ; thiếu key → rỗng (ẩn section, KHÔNG fatal).
- **Rate limit:** mỗi page-load ≤2 API call; trong TTL 0 call. Tab trang riêng = link `?league=` (server-render, KHÔNG JS).
- **KHÔNG đụng:** bot (`bot-press.js`), `wp.js`, front-page.php, single.php, search.php, các helper `inc/query.php`.
- **Class Tailwind mới** → rebuild `dist/main.css`. Comment code tiếng Việt. Escape đầu ra (`esc_html`/`esc_url`).
- Theme conventions: `.container`, `font-hemi ... border-l-4 border-brand pl-4` (heading), `border-card`/`bg-card`/`text-secondary`/`text-brand`, `overflow-x-auto` cho bảng mobile.

---

## File Structure

**Tạo mới:**

| File | Trách nhiệm |
|---|---|
| `wp/themes/bongda247/inc/football-data.php` | Lớp dữ liệu: map, cache, fetch BXH/lịch |
| `wp/themes/bongda247/template-parts/standings-table.php` | Render bảng BXH |
| `wp/themes/bongda247/template-parts/fixtures-list.php` | Render lịch/kết quả |
| `wp/themes/bongda247/page-bang-xep-hang.php` | Trang BXH (tab giải) |
| `wp/themes/bongda247/page-lich-thi-dau.php` | Trang Lịch (tab giải) |

**Sửa:**

| File | Thay đổi |
|---|---|
| `wp/themes/bongda247/functions.php` | `require_once inc/football-data.php` |
| `wp/themes/bongda247/archive.php` | Nhúng BXH + lịch cho category giải |
| `wp/themes/bongda247/header.php` | Nav thêm "BXH" + "Lịch" (desktop + mobile) |
| `wp/themes/bongda247/dist/main.css` | Rebuild (class mới) |

---

## Task 1: Lớp dữ liệu `inc/football-data.php`

**Files:**
- Create: `wp/themes/bongda247/inc/football-data.php`
- Modify: `wp/themes/bongda247/functions.php`

**Interfaces:**
- Consumes: WP core (`wp_remote_get`, `get_option`/`update_option`, `get_transient`/`set_transient`, `add_query_arg`), hằng `FOOTBALL_DATA_KEY`.
- Produces: `BD_FD_LEAGUES` (const), `bd_fd_code($slug)`, `bd_fd_league_name($slug)`, `bd_fd_standings($code) → rows[]{position,name,crest,played,won,draw,lost,gd,points}`, `bd_fd_fixtures($code) → matches[]{utcDate,status,home,homeCrest,away,awayCrest,sh,sa}`.

- [ ] **Step 1: Tạo `inc/football-data.php`**

```php
<?php
defined('ABSPATH') || exit;

// 5 giải VĐQG: slug WordPress → mã competition football-data.org + tên hiển thị.
const BD_FD_LEAGUES = [
    'ngoai-hang-anh' => ['code' => 'PL',  'name' => 'Ngoại hạng Anh'],
    'la-liga'        => ['code' => 'PD',  'name' => 'La Liga'],
    'bundesliga'     => ['code' => 'BL1', 'name' => 'Bundesliga'],
    'serie-a'        => ['code' => 'SA',  'name' => 'Serie A'],
    'ligue-1'        => ['code' => 'FL1', 'name' => 'Ligue 1'],
];

/** slug → mã competition, hoặc null nếu không phải 1 trong 5 giải. */
function bd_fd_code($slug) {
    return BD_FD_LEAGUES[$slug]['code'] ?? null;
}

/** slug → tên giải hiển thị. */
function bd_fd_league_name($slug) {
    return BD_FD_LEAGUES[$slug]['name'] ?? '';
}

/**
 * Gọi API football-data.org. Trả mảng decode, hoặc null nếu thiếu key / lỗi / non-200.
 * Key đọc từ hằng FOOTBALL_DATA_KEY (định nghĩa trong wp-config, KHÔNG commit).
 */
function bd_fd_api($path, $query = []) {
    if (!defined('FOOTBALL_DATA_KEY') || !FOOTBALL_DATA_KEY) {
        return null;
    }
    $url = 'https://api.football-data.org/v4' . $path;
    if (!empty($query)) {
        $url = add_query_arg($query, $url);
    }
    $res = wp_remote_get($url, [
        'headers' => ['X-Auth-Token' => FOOTBALL_DATA_KEY],
        'timeout' => 10,
    ]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return is_array($data) ? $data : null;
}

/**
 * Cache stale-while-revalidate.
 * option 'bd_fd_{key}' giữ data bền; transient 'bd_fd_fresh_{key}' là cờ tươi theo TTL.
 * Còn tươi → trả option. Hết hạn → gọi $fetch: thành công cập nhật cả hai; hỏng (null)
 * → phục vụ option cũ nếu có, else [].
 */
function bd_fd_get($key, $ttl, callable $fetch) {
    $opt_key = 'bd_fd_' . $key;
    $data = get_option($opt_key, null);

    if (get_transient('bd_fd_fresh_' . $key) && $data !== null) {
        return $data;
    }

    $fresh = $fetch();
    if ($fresh !== null) {
        update_option($opt_key, $fresh, false);
        set_transient('bd_fd_fresh_' . $key, 1, $ttl);
        return $fresh;
    }

    return $data ?? [];
}

/** BXH 1 giải theo mã. Mảng hàng. TTL 6h. */
function bd_fd_standings($code) {
    if (!$code) return [];
    return bd_fd_get('standings_' . $code, 6 * HOUR_IN_SECONDS, function () use ($code) {
        $data = bd_fd_api("/competitions/$code/standings");
        if (!$data) return null;
        $table = $data['standings'][0]['table'] ?? [];
        $rows = [];
        foreach ($table as $r) {
            $rows[] = [
                'position' => (int) ($r['position'] ?? 0),
                'name'     => $r['team']['name'] ?? '',
                'crest'    => $r['team']['crest'] ?? '',
                'played'   => (int) ($r['playedGames'] ?? 0),
                'won'      => (int) ($r['won'] ?? 0),
                'draw'     => (int) ($r['draw'] ?? 0),
                'lost'     => (int) ($r['lost'] ?? 0),
                'gd'       => (int) ($r['goalDifference'] ?? 0),
                'points'   => (int) ($r['points'] ?? 0),
            ];
        }
        return $rows;
    });
}

/** Lịch/kết quả trong cửa sổ -7..+14 ngày. Mảng trận. TTL 3h. */
function bd_fd_fixtures($code) {
    if (!$code) return [];
    return bd_fd_get('fixtures_' . $code, 3 * HOUR_IN_SECONDS, function () use ($code) {
        $from = gmdate('Y-m-d', time() - 7 * DAY_IN_SECONDS);
        $to   = gmdate('Y-m-d', time() + 14 * DAY_IN_SECONDS);
        $data = bd_fd_api("/competitions/$code/matches", ['dateFrom' => $from, 'dateTo' => $to]);
        if (!$data) return null;
        $out = [];
        foreach (($data['matches'] ?? []) as $m) {
            $out[] = [
                'utcDate'   => $m['utcDate'] ?? '',
                'status'    => $m['status'] ?? '',
                'home'      => $m['homeTeam']['name'] ?? '',
                'homeCrest' => $m['homeTeam']['crest'] ?? '',
                'away'      => $m['awayTeam']['name'] ?? '',
                'awayCrest' => $m['awayTeam']['crest'] ?? '',
                'sh'        => $m['score']['fullTime']['home'] ?? null,
                'sa'        => $m['score']['fullTime']['away'] ?? null,
            ];
        }
        return $out;
    });
}
```

- [ ] **Step 2: `require_once` trong `functions.php`**

Thêm ngay sau dòng `require_once get_stylesheet_directory() . '/inc/query.php';` (dòng 4):
```php
require_once get_stylesheet_directory() . '/inc/football-data.php';
```

- [ ] **Step 3: Kiểm chứng cache stale-while-revalidate (KHÔNG cần API)**

Run (từ repo root) — test logic cache: option có data cũ, không có cờ tươi, fetch trả null → phải phục vụ data cũ:
```bash
./wp/bin/wp eval '
  update_option("bd_fd_standings_TEST", [["name"=>"STALE","position"=>1]], false);
  delete_transient("bd_fd_fresh_standings_TEST");
  $r = bd_fd_get("standings_TEST", 3600, function(){ return null; });
  echo "stale_fallback=" . ($r[0]["name"] ?? "FAIL") . "\n";
  $r2 = bd_fd_get("standings_TEST", 3600, function(){ return [["name"=>"FRESH","position"=>1]]; });
  echo "refresh=" . ($r2[0]["name"] ?? "FAIL") . "\n";
  echo "fresh_flag=" . (get_transient("bd_fd_fresh_standings_TEST") ? "set" : "none") . "\n";
  delete_option("bd_fd_standings_TEST"); delete_transient("bd_fd_fresh_standings_TEST");
'
```
Expected:
```
stale_fallback=STALE   (hỏng fetch → phục vụ data cũ)
refresh=FRESH          (fetch thành công → cập nhật)
fresh_flag=set         (đã set cờ tươi)
```

- [ ] **Step 4: Kiểm chứng fetch API thật (cần FOOTBALL_DATA_KEY)**

Run:
```bash
./wp/bin/wp eval '
  delete_option("bd_fd_standings_PL"); delete_transient("bd_fd_fresh_standings_PL");
  $r = bd_fd_standings("PL");
  echo "rows=" . count($r) . "\n";
  echo "team0=" . ($r[0]["name"] ?? "NONE") . "\n";
  echo "cached=" . (get_transient("bd_fd_fresh_standings_PL") ? "yes" : "no") . "\n";
'
```
Expected: `rows=20` (Ngoại hạng Anh 20 đội; ≥18 chấp nhận), `team0=` tên 1 đội, `cached=yes`. Nếu `rows=0`/`cached=no` → key chưa set trong wp-config hoặc API lỗi/rate-limit → DỪNG báo BLOCKED với output (ghi rõ có/không có key).

- [ ] **Step 5: Commit**

```bash
git add wp/themes/bongda247/inc/football-data.php wp/themes/bongda247/functions.php
git commit -m "feat(theme): lớp dữ liệu football-data (BXH + lịch, cache stale-while-revalidate)"
```

---

## Task 2: template-parts + nhúng archive

**Files:**
- Create: `wp/themes/bongda247/template-parts/standings-table.php`, `wp/themes/bongda247/template-parts/fixtures-list.php`
- Modify: `wp/themes/bongda247/archive.php`, `wp/themes/bongda247/dist/main.css` (rebuild)

**Interfaces:**
- Consumes: `bd_fd_code`, `bd_fd_standings`, `bd_fd_fixtures` (Task 1); rows/matches shape từ Task 1. Nhận data qua `get_query_var('bd_fd_rows')` / `get_query_var('bd_fd_matches')`.
- Produces: 2 template-part tái dùng (Task 3 dùng lại).

- [ ] **Step 1: Tạo `template-parts/standings-table.php`**

```php
<?php
defined('ABSPATH') || exit;
$rows = get_query_var('bd_fd_rows');
if (empty($rows)) return;
?>
<div class="overflow-x-auto rounded-lg border border-card">
  <table class="w-full text-sm min-w-[560px]">
    <thead class="bg-card text-secondary text-xs uppercase">
      <tr>
        <th class="px-3 py-2 text-left">#</th>
        <th class="px-3 py-2 text-left">Đội</th>
        <th class="px-2 py-2 text-center">Trận</th>
        <th class="px-2 py-2 text-center">T</th>
        <th class="px-2 py-2 text-center">H</th>
        <th class="px-2 py-2 text-center">B</th>
        <th class="px-2 py-2 text-center">HS</th>
        <th class="px-2 py-2 text-center font-bold">Đ</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr class="border-t border-card">
          <td class="px-3 py-2 text-secondary"><?php echo esc_html($r['position']); ?></td>
          <td class="px-3 py-2">
            <span class="flex items-center gap-2">
              <?php if (!empty($r['crest'])) : ?><img src="<?php echo esc_url($r['crest']); ?>" alt="" class="w-5 h-5 object-contain" loading="lazy"><?php endif; ?>
              <span class="truncate"><?php echo esc_html($r['name']); ?></span>
            </span>
          </td>
          <td class="px-2 py-2 text-center text-secondary"><?php echo esc_html($r['played']); ?></td>
          <td class="px-2 py-2 text-center"><?php echo esc_html($r['won']); ?></td>
          <td class="px-2 py-2 text-center"><?php echo esc_html($r['draw']); ?></td>
          <td class="px-2 py-2 text-center"><?php echo esc_html($r['lost']); ?></td>
          <td class="px-2 py-2 text-center text-secondary"><?php echo esc_html(($r['gd'] > 0 ? '+' : '') . $r['gd']); ?></td>
          <td class="px-2 py-2 text-center font-bold text-brand"><?php echo esc_html($r['points']); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 2: Tạo `template-parts/fixtures-list.php`**

```php
<?php
defined('ABSPATH') || exit;
$matches = get_query_var('bd_fd_matches');
if (empty($matches)) return;

// Nhóm theo ngày (giờ VN qua wp_date).
$bd_by_date = [];
foreach ($matches as $m) {
    $ts = strtotime($m['utcDate']);
    if (!$ts) continue;
    $day = wp_date('D, d/m', $ts);
    $m['ts'] = $ts;
    $bd_by_date[$day][] = $m;
}
?>
<div class="space-y-6">
  <?php foreach ($bd_by_date as $day => $list) : ?>
    <div>
      <h4 class="text-xs uppercase tracking-wide text-secondary mb-2"><?php echo esc_html($day); ?></h4>
      <ul class="rounded-lg border border-card">
        <?php foreach ($list as $m) :
            $finished = ($m['status'] === 'FINISHED');
            $mid = $finished ? ($m['sh'] . ' - ' . $m['sa']) : wp_date('H:i', $m['ts']); ?>
          <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm border-t border-card first:border-t-0">
            <span class="flex-1 text-right truncate"><?php echo esc_html($m['home']); ?></span>
            <span class="px-3 font-bold whitespace-nowrap <?php echo $finished ? 'text-brand' : 'text-secondary'; ?>"><?php echo esc_html($mid); ?></span>
            <span class="flex-1 truncate"><?php echo esc_html($m['away']); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</div>
```

- [ ] **Step 3: Nhúng vào `archive.php`**

Trong `wp/themes/bongda247/archive.php`, THÊM khối sau NGAY TRƯỚC dòng `</div>` đóng `<div class="container">` (tức sau khối `if (have_posts()) ... endif;`, trước `</div>` cuối, trước `get_footer()`):

```php
  <?php
  // Dữ liệu bóng đá: chỉ hiện cho archive category là 1 trong 5 giải VĐQG.
  if (is_category() && ($bd_obj = get_queried_object()) && ($bd_code = bd_fd_code($bd_obj->slug))) :
      $bd_rows    = bd_fd_standings($bd_code);
      $bd_matches = bd_fd_fixtures($bd_code);
      if ($bd_rows) : ?>
        <section class="mt-12">
          <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Bảng xếp hạng</h2>
          <?php set_query_var('bd_fd_rows', $bd_rows); get_template_part('template-parts/standings-table'); ?>
        </section>
      <?php endif;
      if ($bd_matches) : ?>
        <section class="mt-12">
          <h2 class="font-hemi text-2xl uppercase border-l-4 border-brand pl-4 mb-6">Lịch thi đấu &amp; kết quả</h2>
          <?php set_query_var('bd_fd_matches', $bd_matches); get_template_part('template-parts/fixtures-list'); ?>
        </section>
      <?php endif;
  endif; ?>
```

- [ ] **Step 4: Rebuild CSS**

```bash
cd wp/themes/bongda247 && npm run build:css
grep -c "overflow-x-auto\|min-w-\[560px\]" dist/main.css
```
Expected: build OK; grep ≥ 1.

- [ ] **Step 5: Kiểm chứng archive giải**

Run (từ repo root):
```bash
curl -s "http://bongda247.local/ngoai-hang-anh/" -o /tmp/bd-cat.html -w "cat: HTTP %{http_code}\n"
echo "Bảng xếp hạng: $(grep -c 'Bảng xếp hạng' /tmp/bd-cat.html)"
echo "Lịch thi đấu: $(grep -c 'Lịch thi đấu' /tmp/bd-cat.html)"
echo "có <table>: $(grep -c '<table' /tmp/bd-cat.html)"
curl -s "http://bongda247.local/chuyen-nhuong/" -o /tmp/bd-cn.html -w "chuyen-nhuong: HTTP %{http_code}\n"
echo "BXH ở chuyen-nhuong (phải 0): $(grep -c 'Bảng xếp hạng' /tmp/bd-cn.html)"
echo "loi PHP: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/bd-cat.html /tmp/bd-cn.html)"
```
Expected: `cat: HTTP 200`; `Bảng xếp hạng` ≥1; `Lịch thi đấu` ≥1; `<table` ≥1; `chuyen-nhuong` HTTP 200 + BXH = 0 (không phải giải VĐQG → ẩn); `loi PHP` = 0.
Nếu BXH ở /ngoai-hang-anh/ = 0 nhưng key đã có (Task 1 Step 4 pass) → DỪNG báo BLOCKED.

- [ ] **Step 6: Commit**

```bash
git add wp/themes/bongda247/template-parts/standings-table.php \
        wp/themes/bongda247/template-parts/fixtures-list.php \
        wp/themes/bongda247/archive.php \
        wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): BXH + lịch trên archive giải"
```

---

## Task 3: Trang riêng BXH/Lịch + nav

**Files:**
- Create: `wp/themes/bongda247/page-bang-xep-hang.php`, `wp/themes/bongda247/page-lich-thi-dau.php`
- Modify: `wp/themes/bongda247/header.php`, `wp/themes/bongda247/dist/main.css` (rebuild)

**Interfaces:**
- Consumes: `BD_FD_LEAGUES`, `bd_fd_code`, `bd_fd_standings`, `bd_fd_fixtures` (Task 1); template-parts `standings-table`/`fixtures-list` (Task 2).
- Produces: trang `/bang-xep-hang/`, `/lich-thi-dau/`.

- [ ] **Step 1: Tạo `page-bang-xep-hang.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Bảng xếp hạng</h1>

  <?php
  $bd_req  = isset($_GET['league']) ? sanitize_key(wp_unslash($_GET['league'])) : '';
  $bd_slug = array_key_exists($bd_req, BD_FD_LEAGUES) ? $bd_req : 'ngoai-hang-anh';
  ?>
  <div class="flex flex-wrap gap-2 mb-8">
    <?php foreach (BD_FD_LEAGUES as $slug => $lg) : ?>
      <a href="<?php echo esc_url(add_query_arg('league', $slug, get_permalink())); ?>"
         class="px-4 py-2 rounded-full text-sm border border-card transition-colors <?php echo $slug === $bd_slug ? 'bg-brand text-white' : 'text-secondary hover:text-brand'; ?>">
        <?php echo esc_html($lg['name']); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php
  $bd_rows = bd_fd_standings(bd_fd_code($bd_slug));
  if ($bd_rows) {
      set_query_var('bd_fd_rows', $bd_rows);
      get_template_part('template-parts/standings-table');
  } else {
      echo '<p class="text-secondary">Chưa có dữ liệu bảng xếp hạng.</p>';
  }
  ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 2: Tạo `page-lich-thi-dau.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Lịch thi đấu</h1>

  <?php
  $bd_req  = isset($_GET['league']) ? sanitize_key(wp_unslash($_GET['league'])) : '';
  $bd_slug = array_key_exists($bd_req, BD_FD_LEAGUES) ? $bd_req : 'ngoai-hang-anh';
  ?>
  <div class="flex flex-wrap gap-2 mb-8">
    <?php foreach (BD_FD_LEAGUES as $slug => $lg) : ?>
      <a href="<?php echo esc_url(add_query_arg('league', $slug, get_permalink())); ?>"
         class="px-4 py-2 rounded-full text-sm border border-card transition-colors <?php echo $slug === $bd_slug ? 'bg-brand text-white' : 'text-secondary hover:text-brand'; ?>">
        <?php echo esc_html($lg['name']); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php
  $bd_matches = bd_fd_fixtures(bd_fd_code($bd_slug));
  if ($bd_matches) {
      set_query_var('bd_fd_matches', $bd_matches);
      get_template_part('template-parts/fixtures-list');
  } else {
      echo '<p class="text-secondary">Chưa có lịch thi đấu.</p>';
  }
  ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 3: Tạo 2 WP Page (WP dùng page-{slug}.php)**

Run (từ repo root) — tạo nếu chưa có (idempotent):
```bash
for pair in "bang-xep-hang|Bảng xếp hạng" "lich-thi-dau|Lịch thi đấu"; do
  SLUG="${pair%%|*}"; TITLE="${pair##*|}"
  EXIST=$(./wp/bin/wp post list --post_type=page --name="$SLUG" --field=ID)
  if [ -z "$EXIST" ]; then
    ./wp/bin/wp post create --post_type=page --post_status=publish --post_title="$TITLE" --post_name="$SLUG" --porcelain
  else
    echo "đã có: $SLUG ($EXIST)"
  fi
done
```
Expected: in ra ID mới cho 2 trang (hoặc "đã có" nếu chạy lại).

- [ ] **Step 4: Thêm "BXH" + "Lịch" vào nav `header.php`**

Trong `wp/themes/bongda247/header.php`, ở **nav desktop** — sau `<?php endforeach; ?>` của vòng `$bd_nav` (bên trong `<ul class="hidden lg:flex ...">`), thêm:
```php
            <li><a href="<?php echo esc_url(home_url('/bang-xep-hang/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">BXH</a></li>
            <li><a href="<?php echo esc_url(home_url('/lich-thi-dau/')); ?>" class="text-sm font-medium uppercase tracking-wide transition-colors text-secondary hover:text-brand">Lịch</a></li>
```
Và trong **mobile menu** (`#bd-mobile-menu`) — sau `<?php endforeach; ?>` của vòng `$bd_nav` mobile (trước khối guard trang tĩnh `$bd_any_page`), thêm:
```php
            <li><a href="<?php echo esc_url(home_url('/bang-xep-hang/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">BXH</a></li>
            <li><a href="<?php echo esc_url(home_url('/lich-thi-dau/')); ?>" class="block py-2 text-sm font-medium uppercase tracking-wide text-secondary hover:text-brand">Lịch</a></li>
```

- [ ] **Step 5: Rebuild CSS**

```bash
cd wp/themes/bongda247 && npm run build:css
grep -c "bg-brand" dist/main.css
```
Expected: build OK; grep ≥ 1 (class tab active).

- [ ] **Step 6: Kiểm chứng trang riêng + nav**

Run (từ repo root):
```bash
curl -s "http://bongda247.local/bang-xep-hang/?league=la-liga" -o /tmp/bxh.html -w "bxh: HTTP %{http_code}\n"
echo "tiêu đề BXH: $(grep -c 'Bảng xếp hạng' /tmp/bxh.html)"
echo "tab giải: $(grep -c 'La Liga' /tmp/bxh.html)"
echo "có table: $(grep -c '<table' /tmp/bxh.html)"
curl -s "http://bongda247.local/lich-thi-dau/" -o /tmp/ltd.html -w "ltd: HTTP %{http_code}\n"
echo "tiêu đề Lịch: $(grep -c 'Lịch thi đấu' /tmp/ltd.html)"
curl -s "http://bongda247.local/" -o /tmp/home.html
echo "nav BXH: $(grep -c '/bang-xep-hang/' /tmp/home.html)"
echo "nav Lịch: $(grep -c '/lich-thi-dau/' /tmp/home.html)"
echo "loi PHP: $(grep -ci 'fatal error\|warning:\|notice:\|deprecated' /tmp/bxh.html /tmp/ltd.html /tmp/home.html)"
```
Expected: `bxh: HTTP 200`, tiêu đề ≥1, tab "La Liga" ≥1, `<table` ≥1; `ltd: HTTP 200`, tiêu đề ≥1; nav BXH ≥1, nav Lịch ≥1; `loi PHP` = 0.
Nếu `bxh` HTTP 404 → 2 trang chưa tạo (Step 3) hoặc slug sai → kiểm tra lại.

- [ ] **Step 7: Commit**

```bash
git add wp/themes/bongda247/page-bang-xep-hang.php \
        wp/themes/bongda247/page-lich-thi-dau.php \
        wp/themes/bongda247/header.php \
        wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): trang riêng BXH + Lịch (tab giải) + nav"
```

---

## Ghi chú (ngoài phạm vi plan)

- **Deploy prod:** (1) `define('FOOTBALL_DATA_KEY', '...')` trong wp-config prod; (2) tạo 2 Page `bang-xep-hang`/`lich-thi-dau` (chạy lại Step 3 với wp-cli prod, hoặc tạo tay).
- WP-Cron warming (làm ấm cache tránh visitor đầu tiên sau TTL chịu latency) — phase sau nếu cần.
- Champions League (bảng nhóm), tỷ số live, H2H — ngoài phạm vi.
