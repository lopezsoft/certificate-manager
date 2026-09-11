import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';
import type * as OpenApiPlugin from 'docusaurus-plugin-openapi-docs';

const config: Config = {
  title: 'MATICERTS.COM',
  tagline:
    'Documentación de la API REST para emitir, consultar y descargar certificados digitales',
  favicon: 'img/logo-circle-blue.ico',
  url: 'https://docs.maticerts.com',
  baseUrl: '/',
  organizationName: 'lopezsoft',
  projectName: 'certificate-manager-docs',
  onBrokenLinks: 'warn',
  markdown: {
    format: 'mdx',
    mermaid: true,
    mdx1Compat: {
      comments: true,
      admonitions: true,
      headingIds: true,
    },
  },
  i18n: {
    defaultLocale: 'es',
    locales: ['es'],
  },

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          docItemComponent: "@theme/ApiItem", // Required for OpenAPI docs
        },
        blog: {
          path: 'changelog',
          routeBasePath: 'changelog',
          blogTitle: 'Changelog',
          blogDescription: 'Registro de cambios y versiones de la API',
          blogSidebarTitle: 'Versiones Recientes',
          blogSidebarCount: 'ALL',
        },
        theme: {
          customCss: './src/css/custom.scss',
        },
        sitemap: {
          changefreq: 'weekly',
          priority: 0.5,
          filename: 'sitemap.xml',
          // La version en desarrollo duplica cada endpoint de la version
          // estable: se excluye para no competir consigo misma en buscadores.
          ignorePatterns: ['/docs/next/**', '/markdown-page'],
        },
      } satisfies Preset.Options,
    ],
  ],

  plugins: [
    'docusaurus-plugin-sass',
    [
      'docusaurus-plugin-openapi-docs',
      {
        id: 'api',
        docsPluginId: 'classic',
        config: {
          api: {
            specPath: '../api-docs.json',
            outputDir: 'docs/api',
            sidebarOptions: {
              groupPathsBy: 'tag',
              categoryLinkSource: 'tag',
            },
          } satisfies OpenApiPlugin.Options,
        }
      },
    ],
    [
      '@docusaurus/plugin-pwa',
      {
        debug: true,
        offlineModeActivationStrategies: [
          'appInstalled',
          'standalone',
          'queryString',
        ],
        pwaHead: [
          {
            tagName: 'link',
            rel: 'icon',
            href: '/img/logo-circle-blue.png',
          },
          {
            tagName: 'link',
            rel: 'manifest',
            href: '/manifest.json',
          },
          {
            tagName: 'meta',
            name: 'theme-color',
            content: '#2556a3',
          },
        ],
      },
    ],
  ],

  themes: ['docusaurus-theme-openapi-docs'],

  themeConfig: {
    // Imagen usada al compartir en redes y mensajeria
    image: 'img/logo-horizontal-blue.png',
    metadata: [
      {
        name: 'keywords',
        content:
          'certificados digitales, API REST, firma electrónica, PKCS#10, ' +
          'P12, OAuth 2.0, MATICERTS, Colombia',
      },
      { name: 'author', content: 'LOPEZSOFT S.A.S.' },
      { name: 'robots', content: 'index, follow' },
      { property: 'og:type', content: 'website' },
      { property: 'og:site_name', content: 'MATICERTS' },
    ],
    colorMode: {
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'MATICERTS.COM',
      logo: {
        alt: 'MATICERTS Logo',
        src: 'img/logo-horizontal-blue.png',
      },
      items: [
        {
          to: '/docs/intro',
          position: 'left',
          label: 'Primeros Pasos',
        },
        {
          type: 'dropdown',
          label: 'Catálogo de API',
          position: 'left',
          items: [
            {
              label: '🗂️ Dashboard General',
              to: '/docs/api',
            },
            {
              label: '🔑 Autenticación',
              to: '/docs/api/autenticacion',
            },
            {
              label: '📄 Emisión de Certificados',
              to: '/docs/api/emision-de-certificados',
            },
            {
              label: '📋 Solicitudes',
              to: '/docs/api/solicitudes-de-certificado',
            },
            {
              label: '⚙️ Configuración',
              to: '/docs/api/configuracion',
            },
            {
              label: '🎫 Tokens (PAT)',
              to: '/docs/api/tokens',
            },
            {
              label: '🖥️ Sistema',
              to: '/docs/api/sistema',
            }
          ]
        },
        {
          to: '/changelog',
          label: 'Changelog',
          position: 'left',
        },
        {
          href: '/certificate-manager-api-v1.postman_collection.json',
          label: 'Descargar Postman Collection',
          position: 'right',
          target: '_blank'
        },
        {
          type: 'dropdown',
          label: 'Ir a la App',
          position: 'right',
          items: [
            {
              label: '🚀 Producción',
              href: 'https://app.maticerts.com/',
            },
            {
              label: '🧪 Sandbox',
              href: 'https://sandbox-app.maticerts.com/',
            },
          ],
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Plataforma',
          items: [
            {
              label: '🚀 App (Producción)',
              href: 'https://app.maticerts.com/',
            },
            {
              label: '🧪 App (Sandbox)',
              href: 'https://sandbox-app.maticerts.com/',
            },
          ],
        },
        {
          title: 'Documentación',
          items: [
            {
              label: 'Primeros Pasos',
              to: '/docs/intro',
            },
            {
              label: 'Catálogo de API',
              to: '/docs/api',
            },
            {
              label: 'Changelog',
              to: '/changelog',
            },
          ],
        },
        {
          title: 'Recursos',
          items: [
            {
              label: 'Postman Collection',
              href: 'pathname:///certificate-manager-api-v1.postman_collection.json',
              target: '_blank',
            },
          ],
        },
      ],
      copyright: `<strong>MATICERTS</strong> · Emisión y gestión de certificados digitales<br/>© ${new Date().getFullYear()} LOPEZSOFT S.A.S. Todos los derechos reservados.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['json', 'bash', 'php', 'typescript'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
