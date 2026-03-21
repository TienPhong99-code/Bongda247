/** @type {import('tailwindcss').Config} */
export default {
  content: ["./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}"],
  darkMode: "class",
  theme: {
    extend: {
      fontFamily: {
        sans: ["Inter", "sans-serif"],
        oswald: ["Oswald", "sans-serif"],
        hemi: ["SVN-Hemi Head", "sans-serif"],
      },
      colors: {
        brand: "#0232ff",
        accent: "#dc2626",
      },
      backgroundColor: {
        site: "#f3f3f3",
        "site-dark": "#0e1217",
        card: "#f5f8fc",
        "card-dark": "#1c1f26",
        button: "#f1f5f9",
        "button-dark": "#1e293b",
        prediction: "#e5e7eb",
        "prediction-dark": "#000000",
      },
      textColor: {
        main: "#0e1217",
        "main-dark": "#ffffff",
        secondary: "#64748b",
        "secondary-dark": "#94a3b8",
        muted: "#94a3b8",
        "muted-dark": "#64748b",
      },
      borderColor: {
        card: "#e2e8f0",
        "card-dark": "#1e293b",
      },
      boxShadow: {
        brand: "0 0 5px #0232ff",
      },
    },
  },
  plugins: [require("@tailwindcss/typography")],
};
