import React from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import Heading from '@theme/Heading';

import { ENVIRONMENTS, type Environment } from './environments';
import styles from './styles.module.css';

function EnvironmentCard({ env }: { env: Environment }) {
  return (
    <div className={clsx(styles.card, styles[env.variant])}>
      <div className={styles.header}>
        <span className={styles.icon} aria-hidden="true">{env.icon}</span>
        <Heading as="h3" className={styles.title}>{env.title}</Heading>
        <span className={styles.badge}>{env.badge}</span>
      </div>

      <p className={styles.description}>{env.description}</p>

      <dl className={styles.urls}>
        <dt className={styles.urlLabel}>Aplicación web</dt>
        <dd className={styles.urlValue}>
          <Link
            className={styles.appLink}
            href={env.appUrl}
            target="_blank"
            rel="noopener noreferrer">
            {env.appLabel}
            <span className={styles.external} aria-hidden="true">↗</span>
          </Link>
        </dd>
        <dt className={styles.urlLabel}>API REST</dt>
        <dd className={styles.urlValue}>
          <code className={styles.apiUrl}>{env.apiUrl}</code>
        </dd>
      </dl>

      <Link
        className={clsx('button button--block', styles.button)}
        href={env.appUrl}
        target="_blank"
        rel="noopener noreferrer">
        {env.buttonLabel}
      </Link>
    </div>
  );
}

export default function EnvironmentCards(): React.ReactElement {
  return (
    <div className={styles.grid}>
      {ENVIRONMENTS.map((env) => (
        <EnvironmentCard key={env.key} env={env} />
      ))}
    </div>
  );
}
