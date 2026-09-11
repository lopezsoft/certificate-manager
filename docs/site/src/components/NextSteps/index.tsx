import React from 'react';
import Link from '@docusaurus/Link';

import styles from './styles.module.css';

type Step = {
  title: string;
  description: string;
  to: string;
  external?: boolean;
};

const STEPS: Step[] = [
  {
    title: 'Genera tu token',
    description: 'Entra al panel de administración y crea tu Personal Access Token.',
    to: 'https://app.maticerts.com/',
    external: true,
  },
  {
    title: 'Explora el catálogo',
    description: 'Descubre todos los endpoints disponibles con ejemplos ejecutables.',
    to: '/docs/api',
  },
  {
    title: 'Guía paso a paso',
    description: 'Implementa tu primer flujo completo de emisión de certificados.',
    to: '/docs/guias/primeros-pasos-paso-a-paso',
  },
  {
    title: 'Casos de uso',
    description: 'Revisa ejemplos reales de integración con ERPs y portales web.',
    to: '/docs/guias/casos-de-uso',
  },
  {
    title: 'Solución de problemas',
    description: 'Resuelve los errores más frecuentes durante la integración.',
    to: '/docs/guias/troubleshooting',
  },
];

/**
 * Lista de destinos, no cuadricula de tarjetas: son rutas alternativas
 * entre las que el lector elige una, y una lista se escanea mas rapido.
 */
export default function NextSteps(): React.ReactElement {
  return (
    <ul className={styles.list}>
      {STEPS.map((step) => (
        <li key={step.title} className={styles.item}>
          <Link
            className={styles.link}
            to={step.external ? undefined : step.to}
            href={step.external ? step.to : undefined}
            target={step.external ? '_blank' : undefined}
            rel={step.external ? 'noopener noreferrer' : undefined}>
            {step.title}
            {step.external && (
              <span className={styles.external} aria-label="(abre en una pestaña nueva)">↗</span>
            )}
          </Link>
          <p className={styles.description}>{step.description}</p>
        </li>
      ))}
    </ul>
  );
}
