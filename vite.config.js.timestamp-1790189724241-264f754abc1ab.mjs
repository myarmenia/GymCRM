// vite.config.js
import { defineConfig } from "file:///C:/Users/HP/Desktop/GYM/GymCRM/node_modules/vite/dist/node/index.js";
import laravel from "file:///C:/Users/HP/Desktop/GYM/GymCRM/node_modules/laravel-vite-plugin/dist/index.js";
import vue from "file:///C:/Users/HP/Desktop/GYM/GymCRM/node_modules/@vitejs/plugin-vue/dist/index.mjs";
var vite_config_default = defineConfig({
  plugins: [
    laravel({
      input: "resources/js/app.js",
      refresh: true
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false
        }
      }
    })
  ],
  // optimizeDeps: {
  //     include: ['jquery', 'select2']
  // },
  resolve: {
    alias: {
      "@": "/resources/js",
      "~bootstrap": "/resources/assets/vendor/js/bootstrap.js"
    }
  },
  optimizeDeps: {
    include: ["jquery", "select2", "bootstrap"]
  },
  // server: {
  //     host: '0.0.0.0',
  //     port: 5173,
  //     hmr: {
  //         host: 'localhost',
  //     },
  // },
  build: {
    commonjsOptions: {
      transformMixedEsModules: true
    }
  }
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFxVc2Vyc1xcXFxIUFxcXFxEZXNrdG9wXFxcXEdZTVxcXFxHeW1DUk1cIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfZmlsZW5hbWUgPSBcIkM6XFxcXFVzZXJzXFxcXEhQXFxcXERlc2t0b3BcXFxcR1lNXFxcXEd5bUNSTVxcXFx2aXRlLmNvbmZpZy5qc1wiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9pbXBvcnRfbWV0YV91cmwgPSBcImZpbGU6Ly8vQzovVXNlcnMvSFAvRGVza3RvcC9HWU0vR3ltQ1JNL3ZpdGUuY29uZmlnLmpzXCI7aW1wb3J0IHsgZGVmaW5lQ29uZmlnIH0gZnJvbSAndml0ZSc7XG5pbXBvcnQgbGFyYXZlbCBmcm9tICdsYXJhdmVsLXZpdGUtcGx1Z2luJztcbmltcG9ydCB2dWUgZnJvbSAnQHZpdGVqcy9wbHVnaW4tdnVlJztcblxuZXhwb3J0IGRlZmF1bHQgZGVmaW5lQ29uZmlnKHtcbiAgICBwbHVnaW5zOiBbXG4gICAgICAgIGxhcmF2ZWwoe1xuICAgICAgICAgICAgaW5wdXQ6ICdyZXNvdXJjZXMvanMvYXBwLmpzJyxcbiAgICAgICAgICAgIHJlZnJlc2g6IHRydWUsXG4gICAgICAgIH0pLFxuICAgICAgICB2dWUoe1xuICAgICAgICAgICAgdGVtcGxhdGU6IHtcbiAgICAgICAgICAgICAgICB0cmFuc2Zvcm1Bc3NldFVybHM6IHtcbiAgICAgICAgICAgICAgICAgICAgYmFzZTogbnVsbCxcbiAgICAgICAgICAgICAgICAgICAgaW5jbHVkZUFic29sdXRlOiBmYWxzZSxcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICB9LFxuICAgICAgICB9KSxcbiAgICBdLFxuXG4gICAgLy8gb3B0aW1pemVEZXBzOiB7XG4gICAgLy8gICAgIGluY2x1ZGU6IFsnanF1ZXJ5JywgJ3NlbGVjdDInXVxuICAgIC8vIH0sXG5cbiAgICByZXNvbHZlOiB7XG4gICAgICAgIGFsaWFzOiB7XG4gICAgICAgICAgICAnQCc6ICcvcmVzb3VyY2VzL2pzJyxcbiAgICAgICAgICAgICd+Ym9vdHN0cmFwJzogJy9yZXNvdXJjZXMvYXNzZXRzL3ZlbmRvci9qcy9ib290c3RyYXAuanMnLFxuICAgICAgICB9LFxuICAgIH0sXG4gICAgb3B0aW1pemVEZXBzOiB7XG4gICAgICAgIGluY2x1ZGU6IFsnanF1ZXJ5JywgJ3NlbGVjdDInLCAnYm9vdHN0cmFwJ10sXG4gICAgfSxcblxuXG4gICAgLy8gc2VydmVyOiB7XG4gICAgLy8gICAgIGhvc3Q6ICcwLjAuMC4wJyxcbiAgICAvLyAgICAgcG9ydDogNTE3MyxcbiAgICAvLyAgICAgaG1yOiB7XG4gICAgLy8gICAgICAgICBob3N0OiAnbG9jYWxob3N0JyxcbiAgICAvLyAgICAgfSxcbiAgICAvLyB9LFxuXG5cbiAgICBidWlsZDoge1xuICAgICAgICBjb21tb25qc09wdGlvbnM6IHtcbiAgICAgICAgICAgIHRyYW5zZm9ybU1peGVkRXNNb2R1bGVzOiB0cnVlLFxuICAgICAgICB9LFxuICAgIH0sXG59KTtcbiJdLAogICJtYXBwaW5ncyI6ICI7QUFBd1IsU0FBUyxvQkFBb0I7QUFDclQsT0FBTyxhQUFhO0FBQ3BCLE9BQU8sU0FBUztBQUVoQixJQUFPLHNCQUFRLGFBQWE7QUFBQSxFQUN4QixTQUFTO0FBQUEsSUFDTCxRQUFRO0FBQUEsTUFDSixPQUFPO0FBQUEsTUFDUCxTQUFTO0FBQUEsSUFDYixDQUFDO0FBQUEsSUFDRCxJQUFJO0FBQUEsTUFDQSxVQUFVO0FBQUEsUUFDTixvQkFBb0I7QUFBQSxVQUNoQixNQUFNO0FBQUEsVUFDTixpQkFBaUI7QUFBQSxRQUNyQjtBQUFBLE1BQ0o7QUFBQSxJQUNKLENBQUM7QUFBQSxFQUNMO0FBQUE7QUFBQTtBQUFBO0FBQUEsRUFNQSxTQUFTO0FBQUEsSUFDTCxPQUFPO0FBQUEsTUFDSCxLQUFLO0FBQUEsTUFDTCxjQUFjO0FBQUEsSUFDbEI7QUFBQSxFQUNKO0FBQUEsRUFDQSxjQUFjO0FBQUEsSUFDVixTQUFTLENBQUMsVUFBVSxXQUFXLFdBQVc7QUFBQSxFQUM5QztBQUFBO0FBQUE7QUFBQTtBQUFBO0FBQUE7QUFBQTtBQUFBO0FBQUEsRUFZQSxPQUFPO0FBQUEsSUFDSCxpQkFBaUI7QUFBQSxNQUNiLHlCQUF5QjtBQUFBLElBQzdCO0FBQUEsRUFDSjtBQUNKLENBQUM7IiwKICAibmFtZXMiOiBbXQp9Cg==
