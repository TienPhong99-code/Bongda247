// sanity/schemaTypes/category.ts
export default {
  name: 'category',
  title: 'Giải đấu',
  type: 'document',
  fields: [
    {
      name: 'title',
      title: 'Tên giải đấu',
      type: 'string',
      description: 'Ví dụ: Ngoại hạng Anh, Champions League',
    },
    {
      name: 'slug',
      title: 'Đường dẫn (Slug)',
      type: 'slug',
      options: {
        source: 'title',
        maxLength: 96,
      },
    },
    {
      name: 'description',
      title: 'Mô tả giải đấu',
      type: 'text',
    },
  ],
}
