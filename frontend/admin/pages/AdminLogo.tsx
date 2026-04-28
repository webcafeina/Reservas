import { useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { Button } from '../../src/components/Button';
import { adminApi } from '../api/client';
import { ApiError } from '../../src/api/client';
import { useAdminLogo } from '../api/hooks';

import styles from './AdminLogo.module.css';

/**
 * Admin-panel logo uploader. Lives inside the Settings page as a
 * dedicated section. Persists the file to wp-content/uploads (so it
 * survives plugin updates) via POST /admin/logo, with a Quitar button
 * that calls DELETE /admin/logo.
 */
export function AdminLogo(): JSX.Element {
    const { data, isLoading, isError } = useAdminLogo();
    const qc = useQueryClient();
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const refresh = async (): Promise<void> => {
        await qc.invalidateQueries({ queryKey: ['admin', 'logo'] });
    };

    const upload = async (file: File): Promise<void> => {
        setBusy(true);
        setError(null);
        try {
            const form = new FormData();
            form.append('file', file);
            const restBase = window.ReservasAldealabAdmin?.restBase ?? '';
            const nonce = window.ReservasAldealabAdmin?.nonce ?? '';
            const res = await fetch(`${restBase}/admin/logo`, {
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
            setBusy(false);
        }
    };

    const remove = async (): Promise<void> => {
        if (
            !window.confirm(
                '¿Eliminar el logo personalizado? El header del panel volverá a mostrarse sin imagen.',
            )
        ) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            await adminApi.delete<{ deleted: boolean }>('/admin/logo');
            await refresh();
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Error al eliminar.');
        } finally {
            setBusy(false);
        }
    };

    if (isLoading) return <p>Cargando logo…</p>;
    if (isError || data === undefined) return <p>No se pudo cargar el estado del logo.</p>;

    const hasCustom = data.source === 'uploads';

    return (
        <div className={styles.wrapper}>
            {error !== null && <p className={styles.error}>{error}</p>}
            <div className={styles.row}>
                <div className={styles.previewBox}>
                    {data.url !== null ? (
                        <img src={data.url} alt="Logo del panel" className={styles.preview} />
                    ) : (
                        <span className={styles.empty}>Sin logo configurado</span>
                    )}
                </div>
                <div className={styles.meta}>
                    <span
                        className={
                            hasCustom
                                ? styles.badgeCustom
                                : data.source === 'packaged'
                                  ? styles.badgePackaged
                                  : styles.badgeNone
                        }
                    >
                        {hasCustom
                            ? 'Personalizado'
                            : data.source === 'packaged'
                              ? 'Empaquetado'
                              : 'Sin logo'}
                    </span>
                    {data.uploaded_at !== null && (
                        <small className={styles.uploadedAt}>Subido: {data.uploaded_at}</small>
                    )}
                    <p className={styles.help}>
                        Formatos aceptados: SVG (preferido) o PNG. Tamaño máximo 2 MB. Se mostrará a
                        la derecha del título del panel, con altura máxima 48 px.
                    </p>
                </div>
                <div className={styles.actions}>
                    <input
                        type="file"
                        accept="image/svg+xml,image/png,.svg,.png"
                        ref={inputRef}
                        style={{ display: 'none' }}
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file !== undefined) {
                                void upload(file);
                            }
                            if (inputRef.current) inputRef.current.value = '';
                        }}
                    />
                    <Button
                        variant="secondary"
                        onClick={() => inputRef.current?.click()}
                        disabled={busy}
                    >
                        {busy ? 'Procesando…' : hasCustom ? 'Cambiar logo' : 'Subir logo'}
                    </Button>
                    {hasCustom && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                void remove();
                            }}
                            disabled={busy}
                        >
                            Quitar logo
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
