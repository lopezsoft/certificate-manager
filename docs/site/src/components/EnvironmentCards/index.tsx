import React from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';

import { ENVIRONMENTS, type Environment } from './environments';
import styles from './styles.module.css';

/**
 * Los entornos son datos comparables: misma estructura, valores distintos.
 * Se presentan como tabla para que la comparacion sea fila a fila, en lugar
 * de obligar a saltar entre dos tarjetas.
 */
function EnvironmentRow({ env }: { env: Environment }) {
  return (
    <div className={clsx(styles.row, styles[env.variant])}>
      <div className={styles.identity}>
        <h3 className={styles.title}>{env.title}</h3>
        <span className={styles.badge}>{env.badge}</span>
        <p className={styles.description}>{env.description}</p>
      </div>

      <dl className={styles.urls}>
        <div className={styles.field}>
          <dt className={styles.fieldLabel}>Aplicación web</dt>
          <dd className={styles.fieldValue}>
            <Link
              className={styles.appLink}
              href={env.appUrl}
              target="_blank"
              rel="noopener noreferrer">
              {env.appLabel}
            </Link>
          </dd>
        </div>
        <div className={styles.field}>
          <dt className={styles.fieldLabel}>API REST</dt>
          <dd className={styles.fieldValue}>
            <code className={styles.apiUrl}>{env.apiUrl}</code>
          </dd>
        </div>
      </dl>
    </div>
  );
}

export default function EnvironmentCards(): React.ReactElement {
  return (
    <div className={styles.table}>
      {ENVIRONMENTS.map((env) => (
        <EnvironmentRow key={env.key} env={env} />
      ))}
    </div>
  );
}
