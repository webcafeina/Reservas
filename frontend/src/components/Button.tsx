import type { ButtonHTMLAttributes, ReactNode } from 'react';

import styles from './Button.module.css';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
    children: ReactNode;
}

export function Button({
    variant = 'primary',
    className,
    children,
    ...rest
}: ButtonProps): JSX.Element {
    const classes = [styles.btn, styles[variant], className].filter(Boolean).join(' ');
    return (
        <button type="button" className={classes} {...rest}>
            {children}
        </button>
    );
}
