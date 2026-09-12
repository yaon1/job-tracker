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
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Канбан откликов на вакансии: импорт с hh.ru, собеседования в календаре, воронка' },
        { name: 'robots', content: 'noindex' },
        { name: 'theme-color', content: '#0c66e4' },
      ],
      link: [{ rel: 'icon', href: "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>" }],
    },
  },
})
