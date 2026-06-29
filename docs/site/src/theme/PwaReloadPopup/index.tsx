import React, {useState} from 'react';
import clsx from 'clsx';
import type {Props} from '@theme/PwaReloadPopup';

import styles from './styles.module.css';

export default function PwaReloadPopup({onReload}: Props): JSX.Element {
  const [isVisible, setIsVisible] = useState(true);

  return (
    isVisible && (
      <div className={clsx('alert alert--secondary', styles.popup)}>
        <p>Una nueva versión está disponible.</p>
        <div className={styles.buttonContainer}>
          <button
            className="button button--link"
            onClick={() => {
              setIsVisible(false);
            }}>
            Cerrar
          </button>
          <button
            className="button button--primary"
            onClick={() => {
              setIsVisible(false);
              onReload();
            }}>
            Actualizar
          </button>
        </div>
      </div>
    )
  );
}
