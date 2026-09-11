/**
 * Fuente única de verdad para los entornos publicados de MATICERTS.
 * Consumido por la portada y por la guía de Primeros Pasos.
 */
export type Environment = {
  key: string;
  title: string;
  badge: string;
  description: string;
  appUrl: string;
  appLabel: string;
  apiUrl: string;
  buttonLabel: string;
  variant: 'production' | 'sandbox';
};

export const ENVIRONMENTS: Environment[] = [
  {
    key: 'production',
    title: 'Producción',
    badge: 'Validez legal',
    description:
      'Infraestructura real. Los certificados emitidos tienen plena validez legal.',
    appUrl: 'https://app.maticerts.com/',
    appLabel: 'app.maticerts.com',
    apiUrl: 'https://api.maticerts.com',
    buttonLabel: 'Entrar a Producción',
    variant: 'production',
  },
  {
    key: 'sandbox',
    title: 'Sandbox',
    badge: 'Gratuito',
    description:
      'Entorno de pruebas para tu ciclo de desarrollo. Sin impacto ni validez legal.',
    appUrl: 'https://sandbox-app.maticerts.com/',
    appLabel: 'sandbox-app.maticerts.com',
    apiUrl: 'https://sandbox-api.maticerts.com',
    buttonLabel: 'Entrar a Sandbox',
    variant: 'sandbox',
  },
];
