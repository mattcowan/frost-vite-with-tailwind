/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './**/*.php',
    './src/**/*.{js,jsx,ts,tsx}',
    // Include parent theme if needed
    '../frost/**/*.php',
  ],
  theme: {
    extend: {
      // Add your custom theme extensions here
    },
  },
  plugins: [],
  // Note: JIT mode is always enabled in Tailwind CSS v4
}
