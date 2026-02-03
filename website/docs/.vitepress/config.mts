import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Notur',
  description: 'Extension framework for Pterodactyl Panel',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/getting-started/installing' },
      { text: 'Extensions', link: '/extensions/guide' },
      { text: 'Admin', link: '/admin/guide' },
      { text: 'Changelog', link: '/reference/changelog' },
      { text: 'Roadmap', link: '/reference/roadmap' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installing Notur', link: '/getting-started/installing' },
          { text: 'Developing Notur', link: '/getting-started/developing' },
        ],
      },
      {
        text: 'Extension Development',
        items: [
          { text: 'Creating Extensions', link: '/extensions/guide' },
          { text: 'PHP API Reference', link: '/extensions/api-reference' },
          { text: 'Frontend SDK', link: '/extensions/frontend-sdk' },
          { text: 'Extension Registry', link: '/extensions/registry' },
        ],
      },
      {
        text: 'Administration',
        items: [
          { text: 'Admin Guide', link: '/admin/guide' },
          { text: 'Extension Registry', link: '/admin/registry' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Changelog', link: '/reference/changelog' },
          { text: 'Roadmap', link: '/reference/roadmap' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/notur/notur' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/notur/notur/edit/main/website/docs/:path',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2024-present Notur Contributors',
    },
  },

  appearance: 'dark',

  vite: {
    css: {
      preprocessorOptions: {
        scss: {
          api: 'modern',
        },
      },
    },
  },
})
