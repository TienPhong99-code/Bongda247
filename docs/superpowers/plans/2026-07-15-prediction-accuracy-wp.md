# SP-A Độ chính xác nhận định (WordPress) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lưu bền dự đoán (CPT `bd_prediction`) + hàm gom số liệu + trang `/thanh-tich-du-doan/` + badge "AI đúng X%".

**Architecture:** CPT `bd_prediction` (mu-plugin, không auto-xoá, ghi qua REST cho bot) → hàm `bd_prediction_stats()` (inc/) gom record `settled` → trang page-template + template-part badge. Test bằng seed data (độc lập bot).

**Tech Stack:** WordPress (PHP 8.2), Tailwind v4 (`npm run build:css`). Test PHP qua `./wp/bin/wp eval-file`; verify curl + Playwright.

**Spec:** `docs/superpowers/specs/2026-07-15-prediction-accuracy-wp-design.md`

## Global Constraints

- Escape MỌI output: `esc_html` (tên/tỉ số/%/số trận), `esc_url` (link). Ký tự ✓/✗ + class là literal (không cần escape).
- KHÔNG gọi API ngoài (số liệu nội bộ từ CPT).
- Meta: **integer** cho số (`match_id, pred_home, pred_away, actual_home, actual_away, outcome_correct, score_correct`), **string** cho chữ (`home_team, away_team, league_code, match_date, pred_text, status, settled_at`). Cờ đúng = integer 0/1 (KHÔNG boolean).
- CPT `bd_prediction`: `public=false`, `show_ui=true`, `show_in_rest=true`, `rest_base='bd_prediction'`. Không có trang single front-end.
- SP-A CHỈ đọc (chấm `outcome_correct`/`score_correct` do SP-B/bot ghi). Chỉ tính record `status='settled'`.
- Bám class theme (`bg-card`/`border-card`, `font-hemi`, `text-brand`/`text-secondary`); page-template bám `page-lich-thi-dau.php`.

---

### Task 1: CPT `bd_prediction` + meta (mu-plugin)

**Files:**
- Modify: `wp/mu-plugins/bongda247-core.php` (thêm CPT + meta, dùng `bd_meta_auth` sẵn có)

**Interfaces:**
- Produces: CPT `bd_prediction` + meta keys (Task 2/3 đọc). REST base `bd_prediction` (SP-B ghi).

- [ ] **Step 1: Thêm đăng ký CPT** — chèn sau hàm `bd_register_match_insight` (sau dòng `}` đóng hàm, trước `add_action('init', 'bd_register_meta')`):

```php
add_action('init', 'bd_register_prediction');
function bd_register_prediction() {
    register_post_type('bd_prediction', [
        'labels' => [
            'name'          => 'Dự đoán',
            'singular_name' => 'Dự đoán',
            'menu_name'     => 'Dự đoán',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'rest_base'    => 'bd_prediction',
        'menu_icon'    => 'dashicons-chart-line',
        'supports'     => ['title', 'custom-fields'],
    ]);
}
```

- [ ] **Step 2: Thêm meta** — trong hàm `bd_register_meta`, ngay trước dòng `// Nguồn bài RSS trên post thường`:

```php
    // Meta CPT bd_prediction — theo dõi độ chính xác nhận định
    foreach (['home_team', 'away_team', 'league_code', 'match_date', 'pred_text', 'status', 'settled_at'] as $key) {
        register_post_meta('bd_prediction', $key, [
            'type'          => 'string',
            'single'        => true,
            'default'       => '',
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }
    foreach (['match_id', 'pred_home', 'pred_away', 'actual_home', 'actual_away', 'outcome_correct', 'score_correct'] as $key) {
        register_post_meta('bd_prediction', $key, [
            'type'          => 'integer',
            'single'        => true,
            'default'       => 0,
            'show_in_rest'  => true,
            'auth_callback' => 'bd_meta_auth',
        ]);
    }
```

- [ ] **Step 3: Verify CPT + meta qua wp-cli**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp post-type list --fields=name,public 2>/dev/null | grep bd_prediction   # có dòng bd_prediction | 
PID=$(./wp/bin/wp post create --post_type=bd_prediction --post_status=publish --post_title="Arsenal vs Chelsea" --porcelain)
./wp/bin/wp post meta update "$PID" status settled >/dev/null
./wp/bin/wp post meta update "$PID" outcome_correct 1 >/dev/null
echo "status=$(./wp/bin/wp post meta get "$PID" status) outcome=$(./wp/bin/wp post meta get "$PID" outcome_correct)"
./wp/bin/wp post delete "$PID" --force
```
Expected: `post-type list` có `bd_prediction`; đọc lại `status=settled outcome=1`; xoá OK.

- [ ] **Step 4: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/mu-plugins/bongda247-core.php
git commit -m "feat(core): CPT bd_prediction + meta (lưu bền dự đoán cho độ chính xác)"
```

---

### Task 2: Hàm `bd_prediction_stats()` (inc/prediction.php)

**Files:**
- Create: `wp/themes/bongda247/inc/prediction.php`
- Modify: `wp/themes/bongda247/functions.php` (require)
- Test (tạm, KHÔNG commit): `/tmp/bd_pred_test.php`

**Interfaces:**
- Consumes: CPT `bd_prediction` + meta (Task 1).
- Produces: `bd_prediction_stats($recent = 10)` → `['total'=>int,'outcome_correct'=>int,'score_correct'=>int,'outcome_pct'=>int,'score_pct'=>int,'recent'=>[ ['home','away','pred_home','pred_away','actual_home','actual_away','outcome_correct','league_code'], ... ]]`. Task 3 dùng.

- [ ] **Step 1: Viết test tạm (thất bại vì hàm chưa có)**

Tạo `/tmp/bd_pred_test.php`:
```php
<?php
// Seed 5 record settled: outcome_correct 1,1,0,0,0 (2/5=40%); score_correct 1,0,0,0,0 (1/5=20%)
$ids = [];
$data = [
  ['A1','B1',2,1,2,1,1,1,'2026-07-15T18:00:00Z'],
  ['A2','B2',1,0,3,1,1,0,'2026-07-14T18:00:00Z'],
  ['A3','B3',0,0,1,2,0,0,'2026-07-13T18:00:00Z'],
  ['A4','B4',2,2,0,1,0,0,'2026-07-12T18:00:00Z'],
  ['A5','B5',1,1,4,0,0,0,'2026-07-11T18:00:00Z'],
];
foreach ($data as $d) {
  $id = wp_insert_post(['post_type'=>'bd_prediction','post_status'=>'publish','post_title'=>$d[0].' vs '.$d[1]]);
  update_post_meta($id,'home_team',$d[0]); update_post_meta($id,'away_team',$d[1]);
  update_post_meta($id,'pred_home',$d[2]); update_post_meta($id,'pred_away',$d[3]);
  update_post_meta($id,'actual_home',$d[4]); update_post_meta($id,'actual_away',$d[5]);
  update_post_meta($id,'outcome_correct',$d[6]); update_post_meta($id,'score_correct',$d[7]);
  update_post_meta($id,'match_date',$d[8]); update_post_meta($id,'status','settled');
  $ids[] = $id;
}
// 1 record pending (không được tính)
$p = wp_insert_post(['post_type'=>'bd_prediction','post_status'=>'publish','post_title'=>'PENDING']);
update_post_meta($p,'status','pending'); update_post_meta($p,'outcome_correct',1);
$ids[] = $p;

$s = bd_prediction_stats(20);
$fail = [];
if ($s['total'] !== 5) $fail[] = 'total='.$s['total'].' (mong 5, loại pending)';
if ($s['outcome_correct'] !== 2) $fail[] = 'outcome_correct='.$s['outcome_correct'].' (mong 2)';
if ($s['score_correct'] !== 1) $fail[] = 'score_correct='.$s['score_correct'].' (mong 1)';
if ($s['outcome_pct'] !== 40) $fail[] = 'outcome_pct='.$s['outcome_pct'].' (mong 40)';
if ($s['score_pct'] !== 20) $fail[] = 'score_pct='.$s['score_pct'].' (mong 20)';
if (count($s['recent']) !== 5) $fail[] = 'recent='.count($s['recent']).' (mong 5)';
// recent sắp match_date DESC → phần tử đầu là A1 (2026-07-15)
if (($s['recent'][0]['home'] ?? '') !== 'A1') $fail[] = 'recent chưa sắp match_date DESC';

foreach ($ids as $id) wp_delete_post($id, true);
echo $fail ? ('FAIL: '.implode(' | ',$fail)."\n") : "PASS\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FATAL (hàm chưa có)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_pred_test.php`
Expected: FATAL `Call to undefined function bd_prediction_stats()`.

- [ ] **Step 3: Tạo `inc/prediction.php`**

```php
<?php
defined('ABSPATH') || exit;

/**
 * Số liệu độ chính xác từ CPT bd_prediction (chỉ record status=settled).
 * @return array ['total','outcome_correct','score_correct','outcome_pct','score_pct','recent']
 */
function bd_prediction_stats($recent = 10) {
    $settled = new WP_Query([
        'post_type'      => 'bd_prediction',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [['key' => 'status', 'value' => 'settled']],
    ]);
    $ids = $settled->posts;
    if ($ids) {
        update_meta_cache('post', $ids);
    }
    $total   = count($ids);
    $outcome = 0;
    $score   = 0;
    foreach ($ids as $id) {
        $outcome += (int) get_post_meta($id, 'outcome_correct', true);
        $score   += (int) get_post_meta($id, 'score_correct', true);
    }

    $rows = [];
    if ($total > 0 && $recent > 0) {
        $recentQ = new WP_Query([
            'post_type'      => 'bd_prediction',
            'posts_per_page' => $recent,
            'no_found_rows'  => true,
            'meta_query'     => [['key' => 'status', 'value' => 'settled']],
            'meta_key'       => 'match_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        ]);
        foreach ($recentQ->posts as $p) {
            $id = $p->ID;
            $rows[] = [
                'home'            => (string) get_post_meta($id, 'home_team', true),
                'away'            => (string) get_post_meta($id, 'away_team', true),
                'pred_home'       => (int) get_post_meta($id, 'pred_home', true),
                'pred_away'       => (int) get_post_meta($id, 'pred_away', true),
                'actual_home'     => (int) get_post_meta($id, 'actual_home', true),
                'actual_away'     => (int) get_post_meta($id, 'actual_away', true),
                'outcome_correct' => (int) get_post_meta($id, 'outcome_correct', true),
                'league_code'     => (string) get_post_meta($id, 'league_code', true),
            ];
        }
        wp_reset_postdata();
    }

    return [
        'total'           => $total,
        'outcome_correct' => $outcome,
        'score_correct'   => $score,
        'outcome_pct'     => $total ? (int) round($outcome * 100 / $total) : 0,
        'score_pct'       => $total ? (int) round($score * 100 / $total) : 0,
        'recent'          => $rows,
    ];
}
```

- [ ] **Step 4: Require trong `functions.php`** — thêm sau dòng require `inc/schema.php`:
```php
require_once get_stylesheet_directory() . '/inc/prediction.php';
```

- [ ] **Step 5: Chạy lại test — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247 && ./wp/bin/wp eval-file /tmp/bd_pred_test.php`
Expected: `PASS`

- [ ] **Step 6: Xoá test tạm + commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
rm -f /tmp/bd_pred_test.php
git add wp/themes/bongda247/inc/prediction.php wp/themes/bongda247/functions.php
git commit -m "feat(theme): bd_prediction_stats — gom % đúng dự đoán từ CPT"
```

---

### Task 3: Trang `/thanh-tich-du-doan/` + badge + tích hợp

**Files:**
- Create: `wp/themes/bongda247/page-thanh-tich-du-doan.php`
- Create: `wp/themes/bongda247/template-parts/prediction-badge.php`
- Modify: `wp/themes/bongda247/page-nhan-dinh.php` (chèn badge đầu trang)
- Build: `wp/themes/bongda247/dist/main.css`

**Interfaces:**
- Consumes: `bd_prediction_stats($recent)` (Task 2).

- [ ] **Step 1: Tạo `template-parts/prediction-badge.php`**

```php
<?php
defined('ABSPATH') || exit;
$bd_s = bd_prediction_stats();
if ($bd_s['total'] === 0) return; // chưa có số liệu → ẩn
?>
<a href="<?php echo esc_url(home_url('/thanh-tich-du-doan/')); ?>" class="inline-flex items-center gap-3 rounded-2xl border border-card bg-card px-5 py-3 hover:border-brand transition-colors">
  <span class="font-hemi text-3xl text-brand"><?php echo esc_html($bd_s['outcome_pct']); ?>%</span>
  <span class="text-sm text-secondary leading-tight">AI dự đoán đúng<br>qua <?php echo esc_html($bd_s['total']); ?> trận →</span>
</a>
```

- [ ] **Step 2: Tạo `page-thanh-tich-du-doan.php`**

```php
<?php defined('ABSPATH') || exit; get_header(); ?>

<div class="container">
  <h1 class="font-hemi text-3xl uppercase border-l-4 border-brand pl-4 mb-6">Thành tích dự đoán</h1>

  <?php $bd_s = bd_prediction_stats(20); ?>
  <?php if ($bd_s['total'] === 0) : ?>
    <p class="text-secondary">Chưa có dữ liệu — quay lại sau khi có trận được chấm.</p>
  <?php else : ?>
    <div class="flex flex-wrap gap-6 mb-8">
      <div class="rounded-2xl border border-card bg-card p-5">
        <div class="font-hemi text-4xl text-brand"><?php echo esc_html($bd_s['outcome_pct']); ?>%</div>
        <div class="text-sm text-secondary mt-1">đúng kết quả (1X2) · <?php echo esc_html($bd_s['total']); ?> trận</div>
      </div>
      <div class="rounded-2xl border border-card bg-card p-5">
        <div class="font-hemi text-4xl"><?php echo esc_html($bd_s['score_pct']); ?>%</div>
        <div class="text-sm text-secondary mt-1">trúng tỉ số chính xác (<?php echo esc_html($bd_s['score_correct']); ?>/<?php echo esc_html($bd_s['total']); ?>)</div>
      </div>
    </div>
    <div class="rounded-2xl border border-card bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-secondary text-xs uppercase">
          <tr class="border-b border-card">
            <th class="text-left px-4 py-3">Trận</th>
            <th class="px-3 py-3">Dự đoán</th>
            <th class="px-3 py-3">Kết quả</th>
            <th class="px-3 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bd_s['recent'] as $r) : ?>
            <tr class="border-t border-card">
              <td class="px-4 py-3"><?php echo esc_html($r['home'] . ' – ' . $r['away']); ?></td>
              <td class="text-center px-3 py-3 text-secondary"><?php echo esc_html($r['pred_home'] . '–' . $r['pred_away']); ?></td>
              <td class="text-center px-3 py-3 font-semibold"><?php echo esc_html($r['actual_home'] . '–' . $r['actual_away']); ?></td>
              <td class="text-center px-3 py-3"><?php echo $r['outcome_correct'] ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">✗</span>'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 3: Chèn badge vào `page-nhan-dinh.php`** — ngay sau dòng `<h1 ...>Nhận định bóng đá</h1>` (trước khối `bd_insights`), thêm:
```php
  <div class="mb-8"><?php get_template_part('template-parts/prediction-badge'); ?></div>
```

- [ ] **Step 4: Tạo WP Page `thanh-tich-du-doan`**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
./wp/bin/wp post create --post_type=page --post_status=publish --post_title="Thành tích dự đoán" --post_name="thanh-tich-du-doan" --porcelain
```
Expected: in ra 1 ID. WordPress dùng `page-thanh-tich-du-doan.php` cho Page slug này.

- [ ] **Step 5: Build CSS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/wp/themes/bongda247 && npm run build:css`
Expected: `Done in ...ms`.

- [ ] **Step 6: Verify — rỗng + có dữ liệu**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
# rỗng
curl -s "http://bongda247.local/thanh-tich-du-doan/" -o /tmp/tt.html -w "HTTP %{http_code}\n"
echo "rỗng: 'Chưa có dữ liệu'=$(grep -c 'Chưa có dữ liệu' /tmp/tt.html) | lỗi PHP=$(grep -ci 'fatal error\|warning:\|notice:' /tmp/tt.html)"
# seed 5 record settled (2 đúng 1X2, 1 trúng tỉ số)
cat > /tmp/tt_seed.php <<'PHP'
<?php
$data=[['A1','B1',2,1,2,1,1,1,'2026-07-15T18:00:00Z'],['A2','B2',1,0,3,1,1,0,'2026-07-14T18:00:00Z'],['A3','B3',0,0,1,2,0,0,'2026-07-13T18:00:00Z'],['A4','B4',2,2,0,1,0,0,'2026-07-12T18:00:00Z'],['A5','B5',1,1,4,0,0,0,'2026-07-11T18:00:00Z']];
$ids=[];
foreach($data as $d){$id=wp_insert_post(['post_type'=>'bd_prediction','post_status'=>'publish','post_title'=>$d[0].' vs '.$d[1]]);update_post_meta($id,'home_team',$d[0]);update_post_meta($id,'away_team',$d[1]);update_post_meta($id,'pred_home',$d[2]);update_post_meta($id,'pred_away',$d[3]);update_post_meta($id,'actual_home',$d[4]);update_post_meta($id,'actual_away',$d[5]);update_post_meta($id,'outcome_correct',$d[6]);update_post_meta($id,'score_correct',$d[7]);update_post_meta($id,'match_date',$d[8]);update_post_meta($id,'status','settled');$ids[]=$id;}
file_put_contents('/tmp/tt_ids.txt',implode(' ',$ids));
echo "seeded ".count($ids)."\n";
PHP
./wp/bin/wp eval-file /tmp/tt_seed.php
curl -s "http://bongda247.local/thanh-tich-du-doan/?x=1" -o /tmp/tt2.html
echo "có dữ liệu: '40%'=$(grep -c '40%' /tmp/tt2.html) | rows(A1..A5)=$(grep -oE 'A[1-5] – B[1-5]' /tmp/tt2.html | wc -l | tr -d ' ') | ✓=$(grep -c '✓' /tmp/tt2.html)"
echo "badge trên /nhan-dinh/: $(curl -s "http://bongda247.local/nhan-dinh/?x=1" | grep -c 'AI dự đoán đúng')"
# dọn
for id in $(cat /tmp/tt_ids.txt); do ./wp/bin/wp post delete $id --force >/dev/null 2>&1; done
rm -f /tmp/tt_seed.php /tmp/tt_ids.txt
echo "sau dọn: 'Chưa có dữ liệu'=$(curl -s "http://bongda247.local/thanh-tich-du-doan/?x=2" | grep -c 'Chưa có dữ liệu')"
```
Expected: rỗng → "Chưa có dữ liệu"=1, lỗi PHP=0; có dữ liệu → "40%"≥1, 5 rows A1–B1..A5–B5, có ✓; badge trên /nhan-dinh/ ≥1; sau dọn → "Chưa có dữ liệu"=1. Nếu lệch → điều tra template, đừng sửa kỳ vọng.

- [ ] **Step 7: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add wp/themes/bongda247/page-thanh-tich-du-doan.php wp/themes/bongda247/template-parts/prediction-badge.php wp/themes/bongda247/page-nhan-dinh.php wp/themes/bongda247/dist/main.css
git commit -m "feat(theme): trang /thanh-tich-du-doan/ + badge độ chính xác + badge trên hub Nhận định"
```

---

## Sau khi xong 3 task

- Cập nhật CLAUDE.md (CPT `bd_prediction`; route `/thanh-tich-du-doan/`; `inc/prediction.php`; template-parts mới) + data model.
- Ledger `.superpowers/sdd/prediction-accuracy-wp/progress.md`.
- Finishing gate (bot `npm test` 9/9; curl trang 200) → merge + push.
- Tiếp theo: **SP-B (Bot)** — ghi record khi đăng + cron đối chiếu kết quả (spec riêng).
