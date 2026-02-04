import { defineConfig } from 'vitepress'
import { groupIconMdPlugin, groupIconVitePlugin } from 'vitepress-plugin-group-icons'

export default defineConfig({
  title: 'Notur',
  description: 'Extension framework for Pterodactyl Panel',

  markdown: {
    mermaid: true,
    config(md) {
      md.use(groupIconMdPlugin)
    },
  },

  vite: {
    plugins: [
      groupIconVitePlugin()
    ],
    css: {
      preprocessorOptions: {
        scss: {
          api: 'modern',
        },
      },
    },
  },

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
          { text: 'Publishing to npm', link: '/getting-started/publishing-npm' },
        ],
      },
      {
        text: 'Extension Development',
        items: [
          { text: 'Creating Extensions', link: '/extensions/guide' },
          { text: 'PHP API Reference', link: '/extensions/api-reference' },
          { text: 'Frontend SDK', link: '/extensions/frontend-sdk' },
          { text: 'Extension Signing', link: '/extensions/signing' },
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
      { icon: 'github', link: 'https://github.com/sak0a/notur' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/sak0a/notur/edit/master/website/docs/:path',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026-present Notur Contributors, a project by sak0a',
    },
  },

  appearance: 'dark',
})
