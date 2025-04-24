import { CoreMenu } from '@core/types'

export const menu: CoreMenu[] = [
  {
    id: 'dashboard',
    title: 'Dashboard',
    // translate: 'MENU.HOME',
    type: 'item',
    icon: 'home',
    url: 'dashboard'
  },
  {
    id: 'documents',
    title: 'Documentos',
    // translate: 'MENU.DOCUMENTS',
    type: 'item',
    icon: 'file',
    url: 'documents'
  },
  {
    id: 'customers',
    title: 'Clientes',
    // translate: 'MENU.DOCUMENTS',
    type: 'item',
    icon: 'file',
    url: 'customers'
  },
  {
    id: 'events',
    title: 'Eventos',
    type: 'item',
    icon: 'file',
    url: 'events',
  },
  {
    id: 'profile',
    title: 'Perfil',
    // translate: 'MENU.DOCUMENTS',
    type: 'item',
    icon: 'file',
    url: 'profile'
  },{
    id: 'settings',
    title: 'Ajustes',
    // translate: 'MENU.DOCUMENTS',
    type: 'item',
    icon: 'file',
    url: 'settings'
  },
  {
    id: 'changes-history',
    title: 'Historial de cambios',
    type: 'item',
    icon: 'list',
    url: 'changes-history',
  }
]
