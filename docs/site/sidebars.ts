import type { SidebarsConfig } from '@docusaurus/plugin-content-docs';
import apiSidebarRaw from "./docs/api/sidebar";

const apiSidebar = apiSidebarRaw.filter(item => item.id !== "api/certificate-manager-api");

const sidebars: SidebarsConfig = {
  tutorialSidebar: [
    {
      type: 'doc',
      id: 'intro',
      label: 'Primeros Pasos'
    },
    {
      type: 'category',
      label: '📚 Guías',
      items: [
        {
          type: 'doc',
          id: 'guias/primeros-pasos-paso-a-paso',
          label: 'Guía Paso a Paso'
        },
        {
          type: 'doc',
          id: 'guias/casos-de-uso',
          label: 'Casos de Uso'
        },
        {
          type: 'doc',
          id: 'guias/troubleshooting',
          label: 'Solución de Problemas'
        }
      ]
    },
    ...apiSidebar,
  ],
};

export default sidebars;
