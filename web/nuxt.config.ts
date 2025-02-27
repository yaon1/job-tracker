export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  ssr: false,
  devtools: { enabled: true },
  css: ['~/assets/main.css'],
  runtimeConfig: { public: { apiBase: 'http://localhost:8000/api' } },
  app: {
    head: {
      htmlAttrs: { lang: 'ru' },
      title: 'Трекер откликов',
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
    },
  },
})
