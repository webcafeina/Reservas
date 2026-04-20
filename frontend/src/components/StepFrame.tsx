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
            <div className={styles.body}>{children}</div>
            {actions !== undefined && <footer className={styles.actions}>{actions}</footer>}
        </section>
    );
}
