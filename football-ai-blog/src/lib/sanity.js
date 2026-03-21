// src/lib/sanity.js
import { createClient } from "@sanity/client";
import { createImageUrlBuilder } from "@sanity/image-url";

export const client = createClient({
  projectId: import.meta.env.PUBLIC_SANITY_PROJECT_ID,
  dataset: import.meta.env.PUBLIC_SANITY_DATASET || "production",
  useCdn: true,
  apiVersion: "2024-03-03",
});

const builder = createImageUrlBuilder(client);

export function urlFor(source) {
  // Nếu source bị null hoặc không có asset, trả về 1 object giả lập có hàm url()
  if (!source || !source.asset) {
    return {
      url: () => "https://placehold.co/400x400/020617/22c55e?text=Bongda247",
      width: () => ({
        height: () => ({
          url: () =>
            "https://placehold.co/400x400/020617/22c55e?text=Bongda247",
        }),
      }),
    };
  }
  return builder.image(source);
}
