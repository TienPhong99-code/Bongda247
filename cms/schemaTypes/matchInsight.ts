// sanity/schemas/matchInsight.ts
export default {
  name: 'matchInsight',
  title: 'Số liệu chuyên sâu',
  type: 'document',
  fields: [
    {name: 'homeTeam', title: 'Đội nhà', type: 'string'},
    {name: 'awayTeam', title: 'Đội khách', type: 'string'},
    {name: 'matchTime', title: 'Giờ đá', type: 'string'},
    {name: 'hot', title: 'Trận hot', type: 'boolean', initialValue: false},
    {name: 'insights', title: 'Các dòng thống kê', type: 'array', of: [{type: 'string'}]},
    {name: 'prediction', title: 'Dự đoán', type: 'string'},
    {
      name: 'matchDate',
      title: 'Thời gian thi đấu (UTC)',
      type: 'datetime',
      description: 'Giờ thi đấu thực tế — dùng để tự động xóa sau khi trận kết thúc',
    },
    {name: 'publishedAt', title: 'Ngày đăng', type: 'datetime'},
  ],
}
