import type {
    InputHTMLAttributes,
    TextareaHTMLAttributes,
    SelectHTMLAttributes,
    ReactNode,
} from 'react';
import { useId } from 'react';

import styles from './Field.module.css';

interface FieldWrapperProps {
    label: string;
    htmlFor: string;
    hint?: string | undefined;
    error?: string | null | undefined;
    required?: boolean | undefined;
    children: ReactNode;
}

function FieldWrapper({
    label,
    htmlFor,
    hint,
    error,
    required,
    children,
}: FieldWrapperProps): JSX.Element {
    return (
        <div className={styles.field}>
            <label htmlFor={htmlFor} className={styles.label}>
                {label}
                {required === true && <span className={styles.required}> *</span>}
            </label>
            {children}
            {hint !== undefined && error == null && <span className={styles.hint}>{hint}</span>}
            {error != null && error !== '' && <span className={styles.error}>{error}</span>}
        </div>
    );
}

type BaseProps = {
    label: string;
    hint?: string;
    error?: string | null;
    required?: boolean;
};

type TextFieldProps = BaseProps & Omit<InputHTMLAttributes<HTMLInputElement>, 'id'>;

export function TextField({ label, hint, error, required, ...rest }: TextFieldProps): JSX.Element {
    const id = useId();
    return (
        <FieldWrapper label={label} htmlFor={id} hint={hint} error={error} required={required}>
            <input
                id={id}
                {...rest}
                className={`${styles.input} ${error != null && error !== '' ? styles.invalid : ''}`}
            />
        </FieldWrapper>
    );
}

type TextareaFieldProps = BaseProps & Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'id'>;

export function TextareaField({
    label,
    hint,
    error,
    required,
    rows = 3,
    ...rest
}: TextareaFieldProps): JSX.Element {
    const id = useId();
    return (
        <FieldWrapper label={label} htmlFor={id} hint={hint} error={error} required={required}>
            <textarea
                id={id}
                rows={rows}
                {...rest}
                className={`${styles.input} ${error != null && error !== '' ? styles.invalid : ''}`}
            />
        </FieldWrapper>
    );
}

interface SelectFieldProps extends BaseProps, Omit<SelectHTMLAttributes<HTMLSelectElement>, 'id'> {
    options: Array<{ value: string; label: string }>;
}

export function SelectField({
    label,
    hint,
    error,
    required,
    options,
    ...rest
}: SelectFieldProps): JSX.Element {
    const id = useId();
    return (
        <FieldWrapper label={label} htmlFor={id} hint={hint} error={error} required={required}>
            <select
                id={id}
                {...rest}
                className={`${styles.input} ${error != null && error !== '' ? styles.invalid : ''}`}
            >
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
        </FieldWrapper>
    );
}
