export default {
  name: 'post',
  title: 'Tin tức bóng đá',
  type: 'document',
  fields: [
    {name: 'title', title: 'Tiêu đề', type: 'string'},
    {name: 'slug', title: 'Slug', type: 'slug', options: {source: 'title'}},
    {
      name: 'category',
      title: 'Thuộc giải đấu',
      type: 'reference',
      to: [{type: 'category'}],
    },
    {name: 'excerpt', title: 'Tóm tắt bài viết', type: 'text', rows: 3},
    {name: 'mainImage', title: 'Ảnh bài viết', type: 'image', options: {hotspot: true}},
    {
      name: 'content',
      title: 'Nội dung chi tiết',
      type: 'array',
      of: [
        {type: 'block'}, // Cho phép viết chữ
        {type: 'image', options: {hotspot: true}}, // Cho phép chèn ảnh vào giữa các đoạn
      ],
    },
    {name: 'publishedAt', title: 'Ngày đăng', type: 'datetime'},
  ],
}
