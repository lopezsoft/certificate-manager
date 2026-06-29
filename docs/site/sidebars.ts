import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';
import apiSidebarRaw from "./docs/api/sidebar";

const apiSidebar = apiSidebarRaw.filter(item => item.id !== "api/certificate-manager-api");

const sidebars: SidebarsConfig = {
  tutorialSidebar: [
    {
      type: 'doc',
      id: 'intro',
      label: 'Primeros Pasos'
    },
    ...apiSidebar,
  ],
};

export default sidebars;
