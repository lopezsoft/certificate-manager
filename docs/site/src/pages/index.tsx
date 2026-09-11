import React from 'react';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';

import EnvironmentCards from '@site/src/components/EnvironmentCards';

import styles from './index.module.css';

/**
 * Portada de la documentacion.
 *
 * Es una pantalla de transito: su unico trabajo es llevar al integrador
 * a su primera peticion. Sin hero de marketing ni tarjetas de features;
 * el contenido real vive en /docs.
 */
function Masthead() {
  const { siteConfig } = useDocusaurusContext();

  return (
    <header className={styles.masthead}>
      <div className="container">
        <Heading as="h1" className={styles.title}>
          {siteConfig.title}
        </Heading>
        <p className={styles.lead}>
          API REST para emitir, consultar y descargar certificados digitales
          desde tu backend. Autenticas con un token, creas la solicitud y
          recuperas el archivo P12.
        </p>

        <nav className={styles.entry} aria-label="Accesos principales">
          <Link className={styles.entryPrimary} to="/docs/intro">
            Empezar
          </Link>
          <Link className={styles.entryLink} to="/docs/api">
            Referencia de la API
          </Link>
          <Link
            className={styles.entryLink}
            to="/certificate-manager-api-v1.postman_collection.json"
            target="_blank">
            Colección de Postman
          </Link>
        </nav>
      </div>
    </header>
  );
}

/**
 * La primera decision real del integrador es a que entorno apunta,
 * asi que la portada la resuelve antes de que entre a la documentacion.
 */
function Environments() {
  return (
    <section className={styles.environments}>
      <div className="container">
        <Heading as="h2" className={styles.sectionTitle}>
          Entornos
        </Heading>
        <p className={styles.sectionNote}>
          Sandbox y Producción son independientes: cuentas, tokens y
          certificados no se comparten entre ellos.
        </p>
        <EnvironmentCards />
      </div>
    </section>
  );
}

export default function Home(): JSX.Element {
  const { siteConfig } = useDocusaurusContext();

  return (
    <Layout
      title={`Inicio | ${siteConfig.title}`}
      description="Documentación de la API de MATICERTS: emisión y gestión de certificados digitales.">
      <Masthead />
      <main>
        <Environments />
      </main>
    </Layout>
  );
}
