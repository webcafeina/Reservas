import { useMemo } from 'react';

import { Button } from '../../src/components/Button';
import { useHealthChecks, type HealthCheck, type HealthSeverity } from '../api/hooks';
import { navigate } from '../useHashRoute';

import styles from './Health.module.css';

const SEVERITY_ICON: Record<HealthSeverity, string> = {
    ok: '✓',
    warn: '!',
    error: '✗',
    info: 'i',
};

const SEVERITY_ICON_CLASS: Record<HealthSeverity, string> = {
    ok: styles.iconOk ?? '',
    warn: styles.iconWarn ?? '',
    error: styles.iconError ?? '',
    info: styles.iconInfo ?? '',
};

/**
 * Strip the leading "#/" from a hash-based fix link so we can pass the
 * remainder to navigate(). External (http://) URLs fall through unchanged
 * and open in a new tab.
 */
function isInternalHash(url: string): boolean {
    return url.startsWith('#/') || url.startsWith('/');
}

function fixLinkTarget(url: string): string {
    return url.replace(/^#\//, '').replace(/^\//, '');
}

export function Health(): JSX.Element {
    const { data, isLoading, isFetching, isError, refetch } = useHealthChecks();

    const grouped = useMemo(() => {
        const out: Array<{ category: string; items: HealthCheck[] }> = [];
        if (data === undefined) return out;
        const seen = new Map<string, HealthCheck[]>();
        for (const c of data.checks) {
            const list = seen.get(c.category) ?? [];
            list.push(c);
            seen.set(c.category, list);
        }
        for (const [category, items] of seen) {
            out.push({ category, items });
        }
        return out;
    }, [data]);

    if (isLoading) {
        return <p className={styles.empty}>Comprobando servicios…</p>;
    }
    if (isError || data === undefined) {
        return (
            <div className={styles.wrapper}>
                <p className={styles.empty}>No se pudo ejecutar la comprobación de estado.</p>
                <div>
                    <Button
                        onClick={() => {
                            void refetch();
                        }}
                    >
                        Reintentar
                    </Button>
                </div>
            </div>
        );
    }

    const { summary } = data;
    const allGreen = summary.error === 0 && summary.warn === 0;

    return (
        <div className={styles.wrapper}>
            <header className={styles.header}>
                <div className={styles.summary}>
                    <span className={`${styles.pill} ${styles.pillOk}`}>{summary.ok} OK</span>
                    {summary.warn > 0 && (
                        <span className={`${styles.pill} ${styles.pillWarn}`}>
                            {summary.warn} aviso{summary.warn === 1 ? '' : 's'}
                        </span>
                    )}
                    {summary.error > 0 && (
                        <span className={`${styles.pill} ${styles.pillError}`}>
                            {summary.error} error{summary.error === 1 ? '' : 'es'}
                        </span>
                    )}
                    {summary.info > 0 && (
                        <span className={`${styles.pill} ${styles.pillInfo}`}>
                            {summary.info} info
                        </span>
                    )}
                </div>
                <Button
                    variant="secondary"
                    onClick={() => {
                        void refetch();
                    }}
                    disabled={isFetching}
                >
                    {isFetching ? 'Comprobando…' : '↻ Actualizar'}
                </Button>
            </header>

            {allGreen && (
                <div className={styles.allGreenBanner}>
                    Todos los servicios funcionan correctamente.
                </div>
            )}

            {grouped.map((group) => (
                <section key={group.category} className={styles.section}>
                    <h3>{group.category}</h3>
                    <ul className={styles.checkList}>
                        {group.items.map((c) => (
                            <li key={c.id} className={styles.check}>
                                <span
                                    className={`${styles.icon} ${SEVERITY_ICON_CLASS[c.severity]}`}
                                    aria-hidden="true"
                                >
                                    {SEVERITY_ICON[c.severity]}
                                </span>
                                <div className={styles.body}>
                                    <span className={styles.label}>{c.label}</span>
                                    <span
                                        className={styles.message}
                                        dangerouslySetInnerHTML={{
                                            __html: renderInlineCode(c.message),
                                        }}
                                    />
                                </div>
                                {c.fix_url !== null && c.fix_url !== '' && (
                                    <FixLink url={c.fix_url} />
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}

function FixLink({ url }: { url: string }): JSX.Element {
    if (isInternalHash(url)) {
        return (
            <a
                href={url.startsWith('#') ? url : `#${url}`}
                className={styles.fixLink}
                onClick={(e) => {
                    e.preventDefault();
                    navigate(fixLinkTarget(url));
                }}
            >
                Arreglar →
            </a>
        );
    }
    return (
        <a href={url} className={styles.fixLink} target="_blank" rel="noopener noreferrer">
            Abrir →
        </a>
    );
}

/**
 * Tiny formatter: turn `text in backticks` into <code>...</code> while
 * still escaping the surrounding text. We control the message strings
 * server-side so this is safe, but the regex stays narrow regardless.
 */
function renderInlineCode(message: string): string {
    const escape = (s: string): string =>
        s
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    const parts = message.split(/`([^`]+)`/g);
    return parts.map((p, i) => (i % 2 === 1 ? `<code>${escape(p)}</code>` : escape(p))).join('');
}
