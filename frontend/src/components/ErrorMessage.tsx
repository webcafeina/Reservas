import styles from './ErrorMessage.module.css';

interface ErrorMessageProps {
    title?: string;
    message: string;
}

export function ErrorMessage({ title, message }: ErrorMessageProps): JSX.Element {
    return (
        <div role="alert" className={styles.box}>
            {title !== undefined && <strong className={styles.title}>{title}</strong>}
            <p className={styles.message}>{message}</p>
        </div>
    );
}
