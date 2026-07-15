# SP-B (Bot): Độ chính xác nhận định AI — ghi + đối chiếu — Design

> Phần Bot của tính năng: ghi dự đoán vào CPT `bd_prediction` khi đăng slide, cron đối chiếu kết quả football-data theo `match_id`, chấm 1X2 + tỉ số, báo Telegram. Hoàn tất feature (SP-A đã có store + hiển thị).

## Mục tiêu

- Tự động **ghi** dự đoán (có `match_id` + tỉ số cấu trúc) khi bot đăng card daily-preview.
- Tự động **đối chiếu** kết quả thật → chấm → cập nhật record → trang `/thanh-tich-du-doan/` có số liệu thật.

## Bối cảnh & ràng buộc

- SP-A đã có: CPT `bd_prediction` (REST base `bd_prediction`, meta `match_id, home_team, away_team, league_code, match_date, pred_home, pred_away, pred_text, status, actual_home, actual_away, outcome_correct, score_correct, settled_at`).
- Bot (`web/bot-press.js`): daily preview lưu `draft` (từ `generateDraftForMatch`) vào `draftStore`; handler **"Đăng lên Slide"** (trong `bot.on("callback_query")`) gọi `wp.createInsight(draft)` rồi `draftStore.delete`.
- `generateDraftForMatch(match, standings)` return `{...gemini, leagueCode, leagueSlug, leagueName, leagueFlag, matchDate}` — **chưa** có `match.id` và tỉ số cấu trúc. `match` (từ `fetchMatchesForDate`) CÓ `id`, `homeTeam.name`, `awayTeam.name`, `utcDate`, `leagueCode`.
- Gemini prompt trả JSON có `prediction` (text). Thêm `predHome`/`predAway`.
- `lib/wp.js`: adapter REST (`createInsight`, `createPost`, `resolveTags`, `deleteById`, ...). Suite test `web/test` (`npm test`, 9/9). `api` = axios instance đã cấu hình WP auth.
- football-data free plan: fetch per-competition `/competitions/{code}/matches?dateFrom&dateTo`; 10 req/phút → delay 7s giữa request (đã có `delay()`).
- Cron dùng `node-cron` timezone `Asia/Ho_Chi_Minh` (pattern các cron sẵn có).

## Quyết định thiết kế (đã chốt)

| # | Quyết định | Chọn |
|---|-----------|------|
| Điểm ghi | Khi nào ghi record | **Khi "Đăng lên Slide"** (1 chỗ; không ghi từ luồng bài Luồng 6) |
| Đối chiếu | Nguồn kết quả | football-data theo `match_id` (fetch per-competition-per-ngày, lọc theo id) — chính xác 100% |
| Chấm | Tiêu chí | **1X2** (dấu pred_home−pred_away vs actual) = chính; **tỉ số chính xác** = phụ |
| Dedup | Tránh trùng | client-side theo `match_id` (fetch pending, bỏ nếu đã có) |
| Cron | Lịch đối chiếu | `0 10 * * *` (10:00 VN, sau khi trận đêm kết thúc) |

## Kiến trúc / thành phần

### 1. `lib/grade.js` (MỚI) — `gradePrediction(pred, actual)` hàm thuần
- **Module riêng, KHÔNG side-effect** (import `bot-press.js` sẽ khởi động cả bot → không test được; nên tách ra `lib/grade.js`).
- Input: `{home, away}` dự đoán + `{home, away}` thật (int). Output: `{outcome_correct: 0|1, score_correct: 0|1}`.
- `Math.sign(a-b)` → 1/0/-1 (thắng/hòa/thua). `outcome_correct = signPred === signActual ? 1 : 0`. `score_correct = predHome===actualHome && predAway===actualAway ? 1 : 0`.
- `export`; `bot-press.js` import dùng ở cron đối chiếu.

### 2. `lib/wp.js` — 3 helper
```js
// find-or-create theo match_id (dedup). matchId hợp lệ + predHome/predAway là số → mới ghi.
export async function createPrediction({ matchId, home, away, leagueCode, matchDate, predHome, predAway, predText }) { ... }
// GET /bd_prediction (per_page 100, orderby date desc) → lọc client-side theo status.
export async function listPredictions({ status } = {}) { ... }
// POST /bd_prediction/{id} cập nhật meta (settled).
export async function settlePrediction(id, { actualHome, actualAway, outcomeCorrect, scoreCorrect }) { ... }
```
- `createPrediction`: fetch `listPredictions({status:'pending'})`; nếu có record `match_id === matchId` → return (bỏ, tránh trùng); else POST `/bd_prediction` với title `${home} vs ${away}`, status publish, meta đầy đủ (status=`pending`).
- `settlePrediction`: POST `/bd_prediction/{id}` meta `{status:'settled', actual_home, actual_away, outcome_correct, score_correct, settled_at: now ISO}`.

### 3. `bot-press.js` — ghi khi đăng slide
- `generateDraftForMatch` return thêm `matchId: match.id`.
- Gemini prompt: thêm `"predHome": số, "predAway": số` vào JSON yêu cầu (mục prediction).
- Handler "Đăng lên Slide": sau `wp.createInsight(...)`, thêm:
  ```js
  if (draft.matchId && Number.isInteger(draft.predHome) && Number.isInteger(draft.predAway)) {
    wp.createPrediction({ matchId: draft.matchId, home: draft.homeTeam, away: draft.awayTeam,
      leagueCode: draft.leagueCode, matchDate: draft.matchDate, predHome: draft.predHome,
      predAway: draft.predAway, predText: draft.prediction }).catch(e => console.warn('⚠️ createPrediction:', e.message));
  }
  ```
  (không `await` chặn / catch nuốt lỗi — đăng slide không được fail vì prediction.)

### 4. `bot-press.js` — cron đối chiếu + notify
- `reconcilePredictions()`:
  1. `const pending = await wp.listPredictions({status:'pending'})`.
  2. Lọc `p.match_date` parse được + `+ 3h < now`.
  3. Gom theo `(league_code, ngày YYYY-MM-DD của match_date)`; với mỗi nhóm: fetch `/competitions/{code}/matches?dateFrom=day&dateTo=day` (delay 7s), map `id → {status, score}`.
  4. Với mỗi pending: tìm match theo `match_id`; nếu `FINISHED` → `gradePrediction` → `settlePrediction`. Đếm settled + đúng.
  5. Gửi Telegram owner: `✅ Đã chấm N dự đoán — đúng X/N (1X2)` (bỏ qua nếu N=0).
- `cron.schedule("0 10 * * *", () => reconcilePredictions(), { timezone: "Asia/Ho_Chi_Minh" })`.
- Lệnh thủ công `/settle` (tùy chọn) chạy `reconcilePredictions()` ngay.

## Data flow

Daily preview → draft (có matchId + predHome/predAway) → user "Đăng lên Slide" → `createInsight` + `createPrediction` (pending) → cron 10:00 → `reconcilePredictions` fetch kết quả theo match_id → `gradePrediction` → `settlePrediction` (settled) → SP-A `/thanh-tich-du-doan/` hiển thị %.

## Error handling

- Gemini thiếu/không hợp lệ `predHome/predAway` → không ghi (không rác).
- `createPrediction` lỗi → log, KHÔNG chặn đăng slide.
- Trận hoãn/huỷ/chưa xong (`status != FINISHED`) → giữ `pending`.
- match_id không thấy trong ngày fetch → giữ `pending` (log).
- football-data timeout/lỗi 1 giải → skip giải đó, chấm tiếp giải khác.
- dedup: match_id đã tồn tại → bỏ ghi.

## Test

- **`gradePrediction`** (unit, `web/test`): case đúng-1X2-sai-tỉ-số (2-1 vs 3-1 → outcome 1, score 0), đúng cả (2-1 vs 2-1 → 1,1), hòa (1-1 vs 0-0 → 1,0), sai (2-1 vs 0-2 → 0,0), hòa-dự-thắng (2-2 vs 2-1 → 0,0). `npm test` xanh.
- **`lib/wp.js`** helper: test `createPrediction` (payload đúng, dedup skip khi match_id tồn tại), `listPredictions` (lọc status), `settlePrediction` (payload settled) — mock `api` như các test wp.js hiện có.
- **Integration nhẹ (local WP):** `createPrediction` → `listPredictions({status:'pending'})` thấy → `settlePrediction` → record `settled` → `curl /thanh-tich-du-doan/` thấy trận + % → xoá record. (chạy tay, không trong suite.)

## Tiêu chí thành công

- [ ] "Đăng lên Slide" tạo record `bd_prediction` (pending) với match_id + tỉ số dự đoán; dedup không trùng.
- [ ] Cron 10:00 (và `/settle`) đối chiếu → chấm 1X2 + tỉ số đúng → record `settled` + báo Telegram.
- [ ] `gradePrediction` + wp.js helper có test; `npm test` xanh.
- [ ] Trang `/thanh-tich-du-doan/` hiển thị số liệu thật sau đối chiếu.
- [ ] Đăng slide KHÔNG fail dù prediction lỗi.

## Ngoài phạm vi

- Ghi từ luồng bài Luồng 6 (chỉ slide). Dedup phía server (REST meta filter). Chấm tay manual insight. Thống kê theo giải/tháng (SP sau). Backfill dự đoán cũ.
