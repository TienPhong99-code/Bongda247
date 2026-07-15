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
