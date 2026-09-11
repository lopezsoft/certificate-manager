import React from 'react';
import Link from '@docusaurus/Link';
import Heading from '@theme/Heading';

import styles from './styles.module.css';

type Step = {
  icon: string;
  title: string;
  description: string;
  to: string;
  external?: boolean;
};

const STEPS: Step[] = [
  {
    icon: '🔑',
    title: 'Genera tu token',
    description: 'Entra al panel de administración y crea tu Personal Access Token.',
    to: 'https://app.maticerts.com/',
    external: true,
  },
  {
    icon: '🗂️',
    title: 'Explora el catálogo',
    description: 'Descubre todos los endpoints disponibles con ejemplos ejecutables.',
    to: '/docs/api',
  },
  {
    icon: '🧭',
    title: 'Guía paso a paso',
    description: 'Implementa tu primer flujo completo de emisión de certificados.',
    to: '/docs/guias/primeros-pasos-paso-a-paso',
  },
  {
    icon: '💡',
    title: 'Casos de uso',
    description: 'Revisa ejemplos reales de integración con ERPs y portales web.',
    to: '/docs/guias/casos-de-uso',
  },
  {
    icon: '🛟',
    title: 'Troubleshooting',
    description: 'Resuelve los errores más frecuentes durante la integración.',
    to: '/docs/guias/troubleshooting',
  },
];

export default function NextSteps(): React.ReactElement {
  return (
    <div className={styles.grid}>
      {STEPS.map((step) => (
        <Link
          key={step.title}
          className={styles.card}
          to={step.external ? undefined : step.to}
          href={step.external ? step.to : undefined}
          target={step.external ? '_blank' : undefined}
          rel={step.external ? 'noopener noreferrer' : undefined}>
          <span className={styles.icon} aria-hidden="true">{step.icon}</span>
          <Heading as="h3" className={styles.title}>
            {step.title}
            {step.external && <span className={styles.external} aria-hidden="true">↗</span>}
          </Heading>
          <p className={styles.description}>{step.description}</p>
        </Link>
      ))}
    </div>
  );
}
