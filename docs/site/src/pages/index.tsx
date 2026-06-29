import React from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';

import styles from './index.module.css';

function HomepageHeader() {
  const { siteConfig } = useDocusaurusContext();
  return (
    <header className={clsx('hero hero--primary', styles.heroBanner)}>
      <div className={styles.heroBackground}></div>
      <div className={clsx('container', styles.heroContainer)}>
        <Heading as="h1" className={styles.heroTitle}>
          {siteConfig.title}
        </Heading>
        <p className={styles.heroSubtitle}>{siteConfig.tagline}</p>
        <div className={styles.buttons}>
          <Link
            className={clsx('button button--secondary button--lg', styles.heroButton)}
            to="/docs/intro">
            Explorar Documentación API 🚀
          </Link>
          <Link
            className={clsx('button button--outline button--secondary button--lg', styles.heroButtonOutline)}
            to="/certificate-manager-api-v1.postman_collection.json"
            target="_blank">
            Descargar Postman
          </Link>
        </div>
      </div>
    </header>
  );
}

function Features() {
  const featureList = [
    {
      title: 'Emisión Zero-Touch',
      icon: '⚡',
      description: 'Generación local de CSR y conexión automática con Viafirma RA mediante polling. Integración fácil y rápida.'
    },
    {
      title: 'Arquitectura Segura',
      icon: '🔐',
      description: 'Seguridad OAuth 2.0 y Personal Access Tokens (PAT). Diseño orientado a microservicios y escalabilidad nativa.'
    },
    {
      title: 'Ambiente Sandbox',
      icon: '🧪',
      description: 'Prueba la API sin impacto real usando el modo Sandbox. Emulación completa del ciclo de emisión local.'
    }
  ];

  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {featureList.map((props, idx) => (
            <div key={idx} className={clsx('col col--4')}>
              <div className={styles.featureCard}>
                <div className={styles.featureIcon}>{props.icon}</div>
                <Heading as="h3" className={styles.featureTitle}>{props.title}</Heading>
                <p className={styles.featureDescription}>{props.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function QuickStart() {
  const steps = [
    {
      number: '1',
      title: 'Obtén tu Token',
      description: 'Genera un Personal Access Token (PAT) desde el panel de administración'
    },
    {
      number: '2',
      title: 'Crea una Solicitud',
      description: 'Envía los datos del solicitante para iniciar el proceso de emisión'
    },
    {
      number: '3',
      title: 'Descarga tu Certificado',
      description: 'Una vez completado, descarga el certificado digital en formato P12'
    }
  ];

  return (
    <section className={styles.quickStart}>
      <div className="container">
        <Heading as="h2" className={styles.quickStartTitle}>
          🚀 Comienza en 3 Pasos
        </Heading>
        <div className="row">
          {steps.map((step, idx) => (
            <div key={idx} className={clsx('col col--4')}>
              <div className={styles.stepCard}>
                <div className={styles.stepNumber}>{step.number}</div>
                <Heading as="h3" className={styles.stepTitle}>{step.title}</Heading>
                <p className={styles.stepDescription}>{step.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}


export default function Home(): JSX.Element {
  const { siteConfig } = useDocusaurusContext();
  return (
    <Layout
      title={`Inicio | ${siteConfig.title}`}
      description="MATICERTS API - Emisión y gestión de certificados digitales.">
      <HomepageHeader />
      <main className={styles.mainContent}>
        <Features />
        <QuickStart />
      </main>
    </Layout>
  );
}
