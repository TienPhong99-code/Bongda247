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
