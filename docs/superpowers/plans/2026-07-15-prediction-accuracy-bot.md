# SP-B Độ chính xác nhận định (Bot) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bot ghi dự đoán vào CPT `bd_prediction` khi đăng slide + cron đối chiếu kết quả football-data theo `match_id` + chấm 1X2/tỉ số + báo Telegram.

**Architecture:** Hàm chấm thuần `lib/grade.js` (unit test); 3 helper REST trong `lib/wp.js` (integration test local WP); sửa `bot-press.js` (draft mang matchId + tỉ số, ghi khi "Đăng lên Slide", `reconcilePredictions()` + cron 10:00 + `/settle`).

**Tech Stack:** Node 20, ESM, `node --test`; Telegraf; axios (football-data + WP REST); node-cron.

**Spec:** `docs/superpowers/specs/2026-07-15-prediction-accuracy-bot-design.md`

## Global Constraints

- `gradePrediction` THUẦN, không side-effect (module `lib/grade.js` riêng — import `bot-press.js` sẽ khởi động bot).
- Chấm: `outcome_correct` = `Math.sign(pred_home−pred_away) === Math.sign(actual_home−actual_away) ? 1 : 0`; `score_correct` = trùng cả 2 số ? 1 : 0.
- Ghi dự đoán CHỈ ở handler "Đăng lên Slide" (`action.startsWith("dapprove_")`). Chỉ ghi khi `matchId` + `predHome`/`predAway` là **số nguyên** hợp lệ.
- `createPrediction` lỗi → `.catch` log, KHÔNG chặn đăng slide (fire-and-forget).
- Dedup client-side theo `match_id` (bỏ nếu đã có pending trùng).
- Đối chiếu: chỉ chấm khi `status === 'FINISHED'` + có tỉ số; delay 7s giữa mỗi request football-data (rate limit 10/phút); notify Telegram chỉ khi settled > 0.
- Cron `0 10 * * *` timezone `Asia/Ho_Chi_Minh`.
- Test `node --test` (file `.test.mjs`). Test wp.js là **integration** (WP local đang chạy) + cleanup — KHÔNG mock.
- `wp` import là `import * as wp from "./lib/wp.js"` (dùng `wp.createPrediction`, ...).

---

### Task 1: `lib/grade.js` — `gradePrediction()` + unit test

**Files:**
- Create: `web/lib/grade.js`
- Create: `web/test/grade.test.mjs`

**Interfaces:**
- Produces: `gradePrediction(pred, actual)` → `{outcome_correct: 0|1, score_correct: 0|1}` (Task 3 dùng).

- [ ] **Step 1: Viết test (thất bại vì chưa có module)**

Tạo `web/test/grade.test.mjs`:
```js
import { test } from "node:test";
import assert from "node:assert/strict";
import { gradePrediction } from "../lib/grade.js";

test("đúng 1X2 sai tỉ số: 2-1 vs 3-1", () => {
  assert.deepEqual(gradePrediction({ home: 2, away: 1 }, { home: 3, away: 1 }), { outcome_correct: 1, score_correct: 0 });
});
test("đúng cả kết quả lẫn tỉ số: 2-1 vs 2-1", () => {
  assert.deepEqual(gradePrediction({ home: 2, away: 1 }, { home: 2, away: 1 }), { outcome_correct: 1, score_correct: 1 });
});
test("hòa đúng, sai tỉ số: 1-1 vs 0-0", () => {
  assert.deepEqual(gradePrediction({ home: 1, away: 1 }, { home: 0, away: 0 }), { outcome_correct: 1, score_correct: 0 });
});
test("sai hẳn: 2-1 vs 0-2", () => {
  assert.deepEqual(gradePrediction({ home: 2, away: 1 }, { home: 0, away: 2 }), { outcome_correct: 0, score_correct: 0 });
});
test("dự thắng nhưng hòa: 2-1 vs 2-2", () => {
  assert.deepEqual(gradePrediction({ home: 2, away: 1 }, { home: 2, away: 2 }), { outcome_correct: 0, score_correct: 0 });
});
```

- [ ] **Step 2: Chạy — kỳ vọng FAIL (module chưa có)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/web && node --test test/grade.test.mjs`
Expected: FAIL — không import được `../lib/grade.js`.

- [ ] **Step 3: Tạo `lib/grade.js`**

```js
/**
 * Chấm 1 dự đoán so với kết quả thật.
 * @param {{home:number, away:number}} pred  tỉ số dự đoán
 * @param {{home:number, away:number}} actual tỉ số thật
 * @returns {{outcome_correct: 0|1, score_correct: 0|1}}
 */
export function gradePrediction(pred, actual) {
  const signPred = Math.sign(pred.home - pred.away);
  const signActual = Math.sign(actual.home - actual.away);
  return {
    outcome_correct: signPred === signActual ? 1 : 0,
    score_correct: pred.home === actual.home && pred.away === actual.away ? 1 : 0,
  };
}
```

- [ ] **Step 4: Chạy lại — kỳ vọng PASS (5 test)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/web && node --test test/grade.test.mjs`
Expected: `# pass 5`, `# fail 0`.

- [ ] **Step 5: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add web/lib/grade.js web/test/grade.test.mjs
git commit -m "feat(bot): gradePrediction — chấm 1X2 + tỉ số (module thuần + test)"
```

---

### Task 2: `lib/wp.js` — createPrediction / listPredictions / settlePrediction + integration test

**Files:**
- Modify: `web/lib/wp.js` (thêm 3 export)
- Create: `web/test/prediction.test.mjs`

**Interfaces:**
- Consumes: CPT `bd_prediction` REST (SP-A). `api` (axios) sẵn trong wp.js.
- Produces: `createPrediction(obj)` → `{id}` hoặc `{skipped:true}`; `listPredictions({status})` → `[{id, ...meta}]`; `settlePrediction(id, obj)` → `{id}`. Task 3 dùng.

- [ ] **Step 1: Viết test integration (thất bại vì hàm chưa có)**

Tạo `web/test/prediction.test.mjs`:
```js
// Integration — chạy thật với WP local (Local phải đang chạy).
import "dotenv/config";
import { test, after } from "node:test";
import assert from "node:assert/strict";
import * as wp from "../lib/wp.js";

const created = [];
after(async () => {
  for (const id of created) {
    try { await wp.deleteById(id, "bd_prediction"); } catch (e) { console.warn("cleanup", id, e.message); }
  }
});

test("createPrediction → dedup → listPredictions → settlePrediction", async () => {
  const matchId = 999000123; // id giả, không đụng dữ liệu thật
  const c = await wp.createPrediction({
    matchId, home: "TestH", away: "TestA", leagueCode: "PL",
    matchDate: "2026-07-15T18:00:00Z", predHome: 2, predAway: 1, predText: "TestH thắng 2-1",
  });
  created.push(c.id);
  assert.equal(typeof c.id, "number");

  // dedup: cùng matchId → skipped
  const dup = await wp.createPrediction({
    matchId, home: "TestH", away: "TestA", leagueCode: "PL",
    matchDate: "2026-07-15T18:00:00Z", predHome: 2, predAway: 1, predText: "x",
  });
  assert.equal(dup.skipped, true);

  // list pending có record vừa tạo
  const pending = await wp.listPredictions({ status: "pending" });
  assert.ok(pending.some((p) => Number(p.match_id) === matchId), "pending thiếu record");

  // settle
  await wp.settlePrediction(c.id, { actualHome: 2, actualAway: 1, outcomeCorrect: 1, scoreCorrect: 1 });
  const settled = await wp.listPredictions({ status: "settled" });
  assert.ok(settled.some((p) => p.id === c.id && Number(p.outcome_correct) === 1), "settle chưa cập nhật");
});
```

- [ ] **Step 2: Chạy — kỳ vọng FAIL (hàm chưa có)**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/web && node --test test/prediction.test.mjs`
Expected: FAIL — `wp.createPrediction is not a function`.

- [ ] **Step 3: Thêm 3 helper vào `lib/wp.js`** (cuối file, cạnh `ensureCategory`/`ensurePage`)

```js
/** Ghi 1 dự đoán (status=pending). Dedup theo match_id — trùng thì bỏ. */
export async function createPrediction({ matchId, home, away, leagueCode, matchDate, predHome, predAway, predText }) {
  const pending = await listPredictions({ status: "pending" });
  if (pending.some((p) => Number(p.match_id) === Number(matchId))) {
    return { skipped: true };
  }
  const res = await api.post("/bd_prediction", {
    title: `${home} vs ${away}`,
    status: "publish",
    meta: {
      match_id: matchId,
      home_team: home,
      away_team: away,
      league_code: leagueCode || "",
      match_date: matchDate || "",
      pred_home: predHome,
      pred_away: predAway,
      pred_text: predText || "",
      status: "pending",
    },
  });
  return { id: res.data.id };
}

/** Danh sách dự đoán (kèm meta). Lọc client-side theo meta status nếu truyền. */
export async function listPredictions({ status } = {}) {
  const res = await api.get("/bd_prediction", {
    params: { per_page: 100, orderby: "date", order: "desc", status: "publish" },
  });
  const rows = res.data.map((p) => ({ id: p.id, ...p.meta }));
  return status ? rows.filter((r) => r.status === status) : rows;
}

/** Cập nhật 1 dự đoán thành settled + kết quả chấm. */
export async function settlePrediction(id, { actualHome, actualAway, outcomeCorrect, scoreCorrect }) {
  await api.post(`/bd_prediction/${id}`, {
    meta: {
      status: "settled",
      actual_home: actualHome,
      actual_away: actualAway,
      outcome_correct: outcomeCorrect,
      score_correct: scoreCorrect,
      settled_at: new Date().toISOString(),
    },
  });
  return { id };
}
```

- [ ] **Step 4: Chạy lại test integration — kỳ vọng PASS**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/web && node --test test/prediction.test.mjs`
Expected: `# pass 1`, `# fail 0` (record test đã cleanup ở `after`).

- [ ] **Step 5: Chạy full suite — không hỏng test cũ**

Run: `cd /Users/hotienphong/Desktop/Personal/Bongda247/web && npm test 2>&1 | grep -E "# (tests|pass|fail)"`
Expected: `# fail 0` (9 cũ + 5 grade + 1 prediction = 15 pass).

- [ ] **Step 6: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add web/lib/wp.js web/test/prediction.test.mjs
git commit -m "feat(bot): wp.js createPrediction/listPredictions/settlePrediction + test"
```

---

### Task 3: bot-press.js — ghi khi đăng slide + cron đối chiếu

**Files:**
- Modify: `web/bot-press.js`

**Interfaces:**
- Consumes: `gradePrediction` (Task 1), `wp.createPrediction/listPredictions/settlePrediction` (Task 2). Sẵn có: `axios`, `FD_BASE`, `FD_HEADERS`, `delay`, `bot`, `OWNER_CHAT_ID`, `cron`, `draftStore`.

- [ ] **Step 1: Import gradePrediction** — cạnh dòng `import * as wp from "./lib/wp.js";` (dòng 6):
```js
import { gradePrediction } from "./lib/grade.js";
```

- [ ] **Step 2: Gemini prompt — thêm tỉ số cấu trúc** trong `generateDraftForMatch`. Thay dòng schema `"prediction": "Tỉ số dự đoán + lý do ngắn (tối đa 10 từ)"` bằng:
```
  "predHome": 0,
  "predAway": 0,
  "prediction": "Tỉ số dự đoán + lý do ngắn (tối đa 10 từ)"
```
Và ngay trên khối JSON (sau dòng `Trả về DUY NHẤT JSON hợp lệ:` hoặc trong phần yêu cầu), thêm 1 dòng hướng dẫn:
```
- predHome, predAway: tỉ số dự đoán dạng SỐ NGUYÊN (VD predHome=2, predAway=1 nghĩa là dự đoán 2-1)
```

- [ ] **Step 3: `generateDraftForMatch` return — thêm matchId + chuẩn hoá predScore.** Trong `return { ...data, leagueCode: ..., matchDate: match.utcDate }`, thêm:
```js
    matchId: match.id,
    predHome: Number.parseInt(data.predHome, 10),
    predAway: Number.parseInt(data.predAway, 10),
```
(đặt cạnh `matchDate`; nếu Gemini không trả → `NaN`, handler sẽ bỏ qua ghi.)

- [ ] **Step 4: Ghi dự đoán khi "Đăng lên Slide".** Trong handler `if (action.startsWith("dapprove_"))`, NGAY SAU `await wp.createInsight({...});` (trước `ctx.answerCbQuery("✅ Đã đăng lên Slide!")`), thêm:
```js
      if (draft.matchId && Number.isInteger(draft.predHome) && Number.isInteger(draft.predAway)) {
        wp.createPrediction({
          matchId: draft.matchId,
          home: draft.homeTeam,
          away: draft.awayTeam,
          leagueCode: draft.leagueCode,
          matchDate: draft.matchDate,
          predHome: draft.predHome,
          predAway: draft.predAway,
          predText: draft.prediction,
        }).catch((e) => console.warn("⚠️ createPrediction:", e.message));
      }
```

- [ ] **Step 5: Thêm `reconcilePredictions()`** — đặt gần các hàm daily preview (trước khối cron ở cuối file):
```js
// Đối chiếu dự đoán pending với kết quả thật → chấm → settle → báo Telegram.
async function reconcilePredictions() {
  let pending;
  try {
    pending = await wp.listPredictions({ status: "pending" });
  } catch (e) {
    console.warn("⚠️ listPredictions:", e.message);
    return;
  }
  const now = Date.now();
  const due = pending.filter((p) => {
    const t = Date.parse(p.match_date);
    return Number.isFinite(t) && t + 3 * 3600 * 1000 < now && p.match_id;
  });
  if (!due.length) return;

  // Gom theo (league_code, ngày) để fetch mỗi giải/ngày 1 lần.
  const groups = {};
  for (const p of due) {
    const key = `${p.league_code}|${String(p.match_date).slice(0, 10)}`;
    (groups[key] ??= []).push(p);
  }

  const resultById = {};
  for (const key of Object.keys(groups)) {
    const [code, day] = key.split("|");
    try {
      const res = await axios.get(`${FD_BASE}/competitions/${code}/matches`, {
        headers: FD_HEADERS,
        params: { dateFrom: day, dateTo: day },
        timeout: 15000,
      });
      for (const m of res.data.matches ?? []) {
        resultById[m.id] = {
          status: m.status,
          home: m.score?.fullTime?.home,
          away: m.score?.fullTime?.away,
        };
      }
    } catch (e) {
      console.warn(`⚠️ reconcile ${code} ${day}:`, e.message);
    }
    await delay(7000);
  }

  let settled = 0;
  let correct = 0;
  for (const p of due) {
    const r = resultById[p.match_id];
    if (!r || r.status !== "FINISHED" || r.home == null || r.away == null) continue;
    const g = gradePrediction(
      { home: Number(p.pred_home), away: Number(p.pred_away) },
      { home: r.home, away: r.away }
    );
    try {
      await wp.settlePrediction(p.id, {
        actualHome: r.home,
        actualAway: r.away,
        outcomeCorrect: g.outcome_correct,
        scoreCorrect: g.score_correct,
      });
      settled++;
      correct += g.outcome_correct;
    } catch (e) {
      console.warn(`⚠️ settle ${p.id}:`, e.message);
    }
  }

  if (settled > 0) {
    await bot.telegram.sendMessage(
      OWNER_CHAT_ID,
      `✅ Đã chấm ${settled} dự đoán — đúng ${correct}/${settled} (kết quả 1X2).`
    );
  }
}
```

- [ ] **Step 6: Cron 10:00 + lệnh `/settle`.** Thêm cron cạnh các `cron.schedule` khác:
```js
// 10:00 sáng — đối chiếu & chấm dự đoán (trận đêm đã kết thúc)
cron.schedule("0 10 * * *", () => reconcilePredictions(), { timezone: "Asia/Ho_Chi_Minh" });
```
Và thêm lệnh thủ công cạnh các `bot.command(...)`:
```js
bot.command("settle", async (ctx) => {
  await ctx.reply("🔄 Đang đối chiếu dự đoán...");
  await reconcilePredictions();
  await ctx.reply("✅ Xong đối chiếu.");
});
```

- [ ] **Step 7: Verify syntax + suite + không hỏng**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247/web
node --check bot-press.js && echo "OK bot-press syntax"
npm test 2>&1 | grep -E "# (tests|pass|fail)"
```
Expected: `OK bot-press syntax`; `# fail 0`.

- [ ] **Step 8: Verify tích hợp (thủ công, ghi vào report):** seed 1 record pending qua `/tmp/rec_seed.php` với `match_id` của 1 trận football-data ĐÃ kết thúc gần đây + `match_date` quá 3h, rồi... *(không bắt buộc chạy — football-data có thể không có trận FINISHED trong cửa sổ; ghi rõ đã kiểm bằng đọc code + `node --check` + suite xanh. Lệnh `/settle` để test live sau.)*

- [ ] **Step 9: Commit**

```bash
cd /Users/hotienphong/Desktop/Personal/Bongda247
git add web/bot-press.js
git commit -m "feat(bot): ghi dự đoán khi đăng slide + cron đối chiếu kết quả + /settle"
```

---

## Sau khi xong 3 task

- Cập nhật CLAUDE.md (Luồng 4: đăng slide → ghi `bd_prediction`; cron 10:00 đối chiếu; lệnh `/settle`; `lib/grade.js` + wp.js helper) + memory bot_architecture.
- Ledger `.superpowers/sdd/prediction-accuracy-bot/progress.md`.
- Finishing gate (`npm test` xanh; `node --check`) → merge + push (Railway auto-deploy bot).
- Feature "Độ chính xác nhận định AI" HOÀN TẤT (SP-A + SP-B).
