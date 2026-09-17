import styles from './WebcafeinaFooter.module.css';

/** Default footer signature; must match SettingsRegistrar::FIRMA_DEFECTO. */
export const FIRMA_DEFECTO = 'Desarrollado con ❤️ y ☕ por Webcafeína | 2026';

/** Max characters of the signature; must match SettingsRegistrar::FIRMA_MAX. */
export const FIRMA_MAX = 120;

const LINK_WORD = 'Webcafeína';

interface WebcafeinaFooterProps {
    /** Signature text from the plugin settings; null or empty hides the footer. */
    text: string | null;
}

export function WebcafeinaFooter({ text }: WebcafeinaFooterProps): JSX.Element | null {
    const firma = (text ?? '').trim();
    if (firma === '') return null;

    // The word "Webcafeína" keeps linking to the agency site, as the fixed
    // signature did; any other text is shown as is.
    const at = firma.indexOf(LINK_WORD);
    return (
        <footer className={styles.footer}>
            {at === -1 ? (
                <span>{firma}</span>
            ) : (
                <span>
                    {firma.slice(0, at)}
                    <a
                        href="https://webcafeina.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        className={styles.link}
                    >
                        {LINK_WORD}
                    </a>
                    {firma.slice(at + LINK_WORD.length)}
                </span>
            )}
        </footer>
    );
}
