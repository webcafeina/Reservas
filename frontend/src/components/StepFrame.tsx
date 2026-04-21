import type { ReactNode } from 'react';

import styles from './StepFrame.module.css';

interface StepFrameProps {
    title: string;
    subtitle?: string;
    children: ReactNode;
    actions?: ReactNode;
}

export function StepFrame({ title, subtitle, children, actions }: StepFrameProps): JSX.Element {
    return (
        <section className={styles.frame}>
            <header className={styles.header}>
                <h2 className={styles.title}>{title}</h2>
                {subtitle !== undefined && <p className={styles.subtitle}>{subtitle}</p>}
            </header>
            {/* Actions are rendered ABOVE the body so users don't have to
             * scroll past long forms (Step 3 fechas, Step 6 perfil…) to
             * find the Back/Next buttons. */}
            {actions !== undefined && <div className={styles.actions}>{actions}</div>}
            <div className={styles.body}>{children}</div>
        </section>
    );
}
