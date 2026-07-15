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
