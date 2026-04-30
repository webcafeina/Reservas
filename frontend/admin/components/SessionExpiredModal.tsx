import { useSessionExpired } from '../state/sessionExpired';

import styles from './SessionExpiredModal.module.css';

/**
 * Full-screen overlay shown when the WP REST nonce or auth cookie has
 * expired (typically because the admin left the panel open for over 24h).
 * Only escape hatch is reloading the page so PHP serialises a fresh nonce.
 */
export function SessionExpiredModal(): JSX.Element | null {
    const isExpired = useSessionExpired((s) => s.isExpired);
    if (!isExpired) return null;

    return (
        <div
            className={styles.overlay}
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="session-expired-title"
            aria-describedby="session-expired-desc"
        >
            <div className={styles.panel}>
                <h2 id="session-expired-title" className={styles.title}>
                    Tu sesión ha caducado
                </h2>
                <p id="session-expired-desc" className={styles.text}>
                    Por seguridad, WordPress ha cerrado tu sesión de trabajo. Recarga la página para
                    seguir gestionando reservas.
                </p>
                <button
                    type="button"
                    className={styles.button}
                    onClick={() => window.location.reload()}
                    autoFocus
                >
                    Recargar la página
                </button>
            </div>
        </div>
    );
}
