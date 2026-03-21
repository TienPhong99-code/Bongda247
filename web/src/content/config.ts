import { defineCollection, z } from "astro:content";

const blog = defineCollection({
  schema: z.object({
    title: z.string(),
    description: z.string(),
    pubDate: z.date(),
    category: z.enum([
      "Ngoại hạng Anh",
      "World Cup 2026",
      "Nhận định",
      "Chuyển nhượng",
    ]),
    image: z.string(),
    author: z.string().default("Football AI"),
    isFeatured: z.boolean().default(false),
  }),
});

export const collections = { blog };
