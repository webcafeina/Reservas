import styles from './WebcafeinaFooter.module.css';

export function WebcafeinaFooter(): JSX.Element {
    return (
        <footer className={styles.footer}>
            <span>
                Desarrollado con <span aria-label="amor">❤️</span> y{' '}
                <span aria-label="café">☕</span> por{' '}
                <a
                    href="https://webcafeina.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    className={styles.link}
                >
                    Webcafeína
                </a>{' '}
                | 2026
            </span>
        </footer>
    );
}
