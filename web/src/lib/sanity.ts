// src/lib/sanity.ts
import { createClient } from "@sanity/client";
import imageUrlBuilder from "@sanity/image-url";
import type { SanityImageSource } from "@sanity/image-url/lib/types/types";

export const client = createClient({
  projectId: import.meta.env.PUBLIC_SANITY_PROJECT_ID,
  dataset: import.meta.env.PUBLIC_SANITY_DATASET || "production",
  useCdn: true,
  apiVersion: "2024-03-03",
});

const builder = imageUrlBuilder(client);

export function urlFor(source: SanityImageSource | null | undefined) {
  // Nếu source bị null hoặc không có asset, trả về URL placeholder
  if (!source) {
    const placeholderUrl =
      "https://placehold.co/400x400/020617/22c55e?text=Bongda247";
    return {
      url: () => placeholderUrl,
      width: (w: number) => ({
        url: () => placeholderUrl,
        height: (h: number) => ({
          url: () => placeholderUrl,
        }),
      }),
      height: (h: number) => ({
        url: () => placeholderUrl,
        width: (w: number) => ({
          url: () => placeholderUrl,
        }),
      }),
    };
  }
  return builder.image(source);
}
