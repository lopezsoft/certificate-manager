import React from 'react';
import clsx from 'clsx';
import Layout from '@theme/Layout';
import Link from '@docusaurus/Link';
import Heading from '@theme/Heading';
import styles from './api.module.css';

const APICategories = [
  {
    title: 'Autenticación',
    icon: '🔑',
    description: 'Endpoints para inicio de sesión, recuperación de contraseña y gestión de la sesión OAuth 2.0.',
    link: '/docs/api/autenticacion'
  },
  {
    title: 'Emisión de Certificados',
    icon: '📄',
    description: 'Generación, renovación, firma y descarga de certificados digitales PKCS#10 y P12.',
    link: '/docs/api/emision-de-certificados'
  },
  {
    title: 'Solicitudes',
    icon: '📋',
    description: 'Control detallado de las solicitudes de certificados. Aprobación, rechazo y seguimiento de estados.',
    link: '/docs/api/solicitudes-de-certificado'
  },
  {
    title: 'Configuración',
    icon: '⚙️',
    description: 'Parámetros del sistema, credenciales de RA, plantillas de correo y ajustes globales.',
    link: '/docs/api/configuracion'
  },
  {
    title: 'Tokens (PAT)',
    icon: '🎫',
    description: 'Creación y revocación de Personal Access Tokens para integraciones Machine-to-Machine.',
    link: '/docs/api/tokens'
  },
  {
    title: 'Sistema',
    icon: '🖥️',
    description: 'Consultas sobre métricas de salud (Health Check), cuotas y analíticas del servidor.',
    link: '/docs/api/sistema'
  }
];

function CategoryCard({ title, icon, description, link }) {
  return (
    <div className="col col--4 margin-bottom--lg">
      <Link to={link} className={clsx('card', styles.categoryCard)}>
        <div className="card__header">
          <div className={styles.categoryIcon}>{icon}</div>
          <Heading as="h3" className={styles.categoryTitle}>{title}</Heading>
        </div>
        <div className="card__body">
          <p className={styles.categoryDescription}>{description}</p>
        </div>
      </Link>
    </div>
  );
}

export default function ApiIndex(): JSX.Element {
  return (
    <Layout
      title="API Reference - Categorías"
      description="Explora los endpoints de la API de MATICERTS organizados por categorías funcionales.">
      <header className={clsx('hero', styles.heroBanner)}>
        <div className="container">
          <Heading as="h1" className="hero__title">
            Catálogo de API
          </Heading>
          <p className="hero__subtitle">
            Explora de manera segmentada todos los recursos, esquemas y endpoints REST disponibles en MATICERTS v1.
          </p>
        </div>
      </header>
      <main className="container margin-top--xl margin-bottom--xl">
        <div className="row">
          {APICategories.map((category, idx) => (
            <CategoryCard key={idx} {...category} />
          ))}
        </div>
        
        <div className={styles.actionContainer}>
            <Link className="button button--secondary button--lg" to="/docs/intro">
              Guía de Primeros Pasos
            </Link>
        </div>
      </main>
    </Layout>
  );
}
