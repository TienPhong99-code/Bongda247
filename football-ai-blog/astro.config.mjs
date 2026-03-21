import { defineConfig } from "astro/config";
import tailwindcss from "@tailwindcss/vite";
import react from "@astrojs/react";

export default defineConfig({
  vite: {
    plugins: [tailwindcss()],
  },
  redirects: {
    "/tin-tuc/[...slug]": "/ngoai-hang-anh/[...slug]",
  },
  integrations: [react()],
});
