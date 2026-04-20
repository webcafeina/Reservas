import { useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { Button } from '../../src/components/Button';
import { adminApi } from '../api/client';
import { ApiError } from '../../src/api/client';
import { usePdfTemplates, type PdfTemplate } from '../api/hooks';

import styles from './PdfTemplates.module.css';

const LABELS: Record<string, string> = {
    aldealab: 'Solicitud genérica (Aldealab)',
    cpa: 'Solicitud CPA (Centro de Producciones Audiovisuales)',
};

export function PdfTemplates(): JSX.Element {
    const { data, isLoading, isError } = usePdfTemplates();
    const qc = useQueryClient();
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const refresh = async (): Promise<void> => {
        await qc.invalidateQueries({ queryKey: ['admin', 'pdf-templates'] });
    };

    const upload = async (key: string, file: File): Promise<void> => {
        setBusyKey(key);
        setError(null);
        try {
            const form = new FormData();
            form.append('file', file);
            const restBase = window.ReservasAldealabAdmin?.restBase ?? '';
            const nonce = window.ReservasAldealabAdmin?.nonce ?? '';
            const res = await fetch(`${restBase}/admin/pdf-templates/${key}`, {
                method: 'POST',
                headers: nonce !== '' ? { 'X-WP-Nonce': nonce } : {},
                credentials: 'same-origin',
                body: form,
            });
            if (!res.ok) {
                const text = await res.text();
                let msg = `HTTP ${res.status}`;
                try {
                    const parsed = JSON.parse(text) as { message?: string };
                    if (parsed.message !== undefined) msg = parsed.message;
                } catch {
                    // ignore
                }
                throw new Error(msg);
            }
            await refresh();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error al subir.');
        } finally {
            setBusyKey(null);
        }
    };

    const revert = async (key: string): Promise<void> => {
        if (!window.confirm('¿Eliminar la plantilla personalizada y volver a la empaquetada?')) {
            return;
        }
        setBusyKey(key);
        setError(null);
        try {
            await adminApi.delete<{ reverted: boolean }>(`/admin/pdf-templates/${key}`);
            await refresh();
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Error al revertir.');
        } finally {
            setBusyKey(null);
        }
    };

    if (isLoading) return <p>Cargando plantillas…</p>;
    if (isError || data === undefined) return <p>No se pudieron cargar las plantillas.</p>;

    return (
        <div className={styles.wrapper}>
            {error !== null && <p className={styles.error}>{error}</p>}
            <ul className={styles.list}>
                {data.items.map((tpl) => (
                    <TemplateRow
                        key={tpl.key}
                        tpl={tpl}
                        busy={busyKey === tpl.key}
                        onUpload={(file) => {
                            void upload(tpl.key, file);
                        }}
                        onRevert={() => {
                            void revert(tpl.key);
                        }}
                    />
                ))}
            </ul>
        </div>
    );
}

interface TemplateRowProps {
    tpl: PdfTemplate;
    busy: boolean;
    onUpload: (file: File) => void;
    onRevert: () => void;
}

function TemplateRow({ tpl, busy, onUpload, onRevert }: TemplateRowProps): JSX.Element {
    const inputRef = useRef<HTMLInputElement | null>(null);

    return (
        <li className={styles.row}>
            <div>
                <strong>{LABELS[tpl.key] ?? tpl.key}</strong>
                <br />
                <small>
                    Archivo: <code>{tpl.filename}</code>
                </small>
                <br />
                <span
                    className={
                        tpl.source === 'custom' ? styles.badgeCustom : styles.badgePackaged
                    }
                >
                    {tpl.source === 'custom' ? 'Personalizada' : 'Empaquetada'}
                </span>
                {tpl.uploaded_at !== null && (
                    <small className={styles.uploadedAt}>
                        Subida: {tpl.uploaded_at}
                    </small>
                )}
            </div>
            <div className={styles.actions}>
                <input
                    type="file"
                    accept="application/pdf"
                    ref={inputRef}
                    style={{ display: 'none' }}
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file !== undefined) onUpload(file);
                        if (inputRef.current) inputRef.current.value = '';
                    }}
                />
                <Button
                    variant="secondary"
                    onClick={() => inputRef.current?.click()}
                    disabled={busy}
                >
                    {busy ? 'Subiendo…' : 'Subir nueva'}
                </Button>
                {tpl.source === 'custom' && (
                    <Button variant="ghost" onClick={onRevert} disabled={busy}>
                        Volver a empaquetada
                    </Button>
                )}
            </div>
        </li>
    );
}
