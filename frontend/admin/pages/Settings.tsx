import { useEffect, useState } from 'react';

import { Button } from '../../src/components/Button';
import { SelectField, TextField, TextareaField } from '../../src/components/Field';
import { ErrorMessage } from '../../src/components/ErrorMessage';
import { useSettings, useUpdateSettings, type Settings as SettingsShape } from '../api/hooks';

import { AdminLogo } from './AdminLogo';
import { PdfTemplates } from './PdfTemplates';

import styles from './Settings.module.css';

export function SettingsPage(): JSX.Element {
    const { data, isLoading, isError } = useSettings();
    const update = useUpdateSettings();

    const [form, setForm] = useState<SettingsShape | null>(null);
    const [emailsText, setEmailsText] = useState('');
    const [feedback, setFeedback] = useState<string | null>(null);

    useEffect(() => {
        if (data !== undefined && form === null) {
            setForm(data);
            setEmailsText(data.admin_emails.join(', '));
        }
    }, [data, form]);

    if (isLoading || form === null) return <p>Cargando ajustes…</p>;
    if (isError) return <ErrorMessage message="No se pudieron cargar los ajustes." />;

    const patch = <K extends keyof SettingsShape>(key: K, value: SettingsShape[K]): void => {
        setForm((prev) => (prev === null ? prev : { ...prev, [key]: value }));
    };

    const save = async (): Promise<void> => {
        if (form === null) return;
        setFeedback(null);
        try {
            const emails = emailsText
                .split(',')
                .map((s) => s.trim())
                .filter((s) => s.length > 0);
            const payload: Partial<SettingsShape> = { ...form, admin_emails: emails };
            // Don't send the mask back as a literal secret value.
            if (payload.turnstile_secret === '__redacted__') {
                delete payload.turnstile_secret;
            }
            if (payload.twilio_auth_token === '__redacted__') {
                delete payload.twilio_auth_token;
            }
            await update.mutateAsync(payload);
            setFeedback('Ajustes guardados.');
        } catch (err) {
            setFeedback(err instanceof Error ? err.message : 'Error al guardar.');
        }
    };

    return (
        <div className={styles.wrapper}>
            <section className={styles.group}>
                <h2>Emails del administrador</h2>
                <TextareaField
                    label="Destinatarios (separados por coma)"
                    value={emailsText}
                    onChange={(e) => setEmailsText(e.target.value)}
                    rows={2}
                    hint="Cada nueva reserva se notificará a estas direcciones."
                />
            </section>

            <section className={styles.group}>
                <h2>Cloudflare Turnstile</h2>
                <p className={styles.muted}>
                    Crea un widget en{' '}
                    <a
                        href="https://dash.cloudflare.com/?to=/:account/turnstile"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Cloudflare Turnstile
                    </a>{' '}
                    y pega aquí las claves. Si las dejas vacías, el plugin no pedirá Turnstile (no
                    recomendado en producción).
                </p>
                <div className={styles.row}>
                    <TextField
                        label="Site key (pública)"
                        value={form.turnstile_site_key}
                        onChange={(e) => patch('turnstile_site_key', e.target.value)}
                    />
                    <TextField
                        label="Secret (privada)"
                        type="password"
                        value={form.turnstile_secret}
                        onChange={(e) => patch('turnstile_secret', e.target.value)}
                        hint="Se guarda cifrada en wp_options. Muestra '__redacted__' si ya hay una configurada."
                    />
                </div>
            </section>

            <section className={styles.group}>
                <h2>Sede Electrónica</h2>
                <div className={styles.row}>
                    <TextField
                        label="URL general de la Sede"
                        value={form.sede_url}
                        onChange={(e) => patch('sede_url', e.target.value)}
                    />
                    <TextField
                        label="URL directa al trámite (opcional)"
                        value={form.sede_tramite_url}
                        onChange={(e) => patch('sede_tramite_url', e.target.value)}
                        hint="Se incluye en los emails con instrucciones para el usuario."
                    />
                </div>
            </section>

            <section className={styles.group}>
                <h2>Infraestructura</h2>
                <div className={styles.row}>
                    <TextField
                        label="Ruta al binario pdftk (opcional)"
                        value={form.pdftk_path}
                        onChange={(e) => patch('pdftk_path', e.target.value)}
                        hint="Déjalo vacío si pdftk está en el PATH del sistema."
                    />
                    <TextField
                        label="URL dev de Vite (desarrollo)"
                        value={form.vite_dev_url}
                        onChange={(e) => patch('vite_dev_url', e.target.value)}
                        hint="p. ej. http://localhost:5173 — déjalo vacío en producción."
                    />
                </div>
            </section>

            <section className={styles.group}>
                <h2>Textos de email</h2>
                <TextareaField
                    label="Introducción en el email al usuario"
                    value={form.email_intro_user}
                    onChange={(e) => patch('email_intro_user', e.target.value)}
                    rows={3}
                    hint="Opcional. Se añade bajo el saludo."
                />
                <TextareaField
                    label="Introducción en el email al administrador"
                    value={form.email_intro_admin}
                    onChange={(e) => patch('email_intro_admin', e.target.value)}
                    rows={3}
                />
            </section>

            <section className={styles.group}>
                <h2>Notificaciones SMS (opcional)</h2>
                <p className={styles.muted}>
                    Si activas un proveedor SMS, cada confirmación / cancelación enviará también un
                    SMS al móvil del solicitante. Si lo dejas en «Ninguno», no se envía ningún SMS.
                </p>
                <SelectField
                    label="Proveedor"
                    value={form.sms_provider}
                    onChange={(e) =>
                        patch('sms_provider', e.target.value as SettingsShape['sms_provider'])
                    }
                    options={[
                        { value: 'none', label: 'Ninguno (desactivado)' },
                        { value: 'twilio', label: 'Twilio' },
                    ]}
                />
                {form.sms_provider === 'twilio' && (
                    <div className={styles.row}>
                        <TextField
                            label="Twilio Account SID"
                            value={form.twilio_account_sid}
                            onChange={(e) => patch('twilio_account_sid', e.target.value)}
                        />
                        <TextField
                            label="Twilio Auth Token"
                            type="password"
                            value={form.twilio_auth_token}
                            onChange={(e) => patch('twilio_auth_token', e.target.value)}
                            hint="Guardado cifrado. Muestra '__redacted__' si ya hay uno."
                        />
                        <TextField
                            label="Número emisor (E.164)"
                            value={form.twilio_from_number}
                            onChange={(e) => patch('twilio_from_number', e.target.value)}
                            hint="Formato internacional, p. ej. +34600123456."
                        />
                    </div>
                )}
            </section>

            <section className={styles.group}>
                <h2>Logo del panel</h2>
                <p className={styles.muted}>
                    Imagen que se muestra a la derecha del título «Gestor de reservas de AldeaLab»
                    en el header del panel. El archivo se guarda en wp-content/uploads/, por lo que
                    persiste a actualizaciones del plugin.
                </p>
                <AdminLogo />
            </section>

            <section className={styles.group}>
                <h2>Plantillas PDF</h2>
                <p className={styles.muted}>
                    Sube una plantilla oficial actualizada si el Ayuntamiento la revisa. Si se
                    elimina la personalizada, el plugin usa la que viene empaquetada.
                </p>
                <PdfTemplates />
            </section>

            <section className={styles.group}>
                <h2>Desinstalación</h2>
                <label className={styles.checkbox}>
                    <input
                        type="checkbox"
                        checked={form.delete_on_uninstall}
                        onChange={(e) => patch('delete_on_uninstall', e.target.checked)}
                    />
                    <span>
                        <strong>Eliminar todos los datos al desinstalar el plugin.</strong>
                        <br />
                        <small>
                            Borra tablas, reservas, perfiles y logs. Solo actívalo si sabes lo que
                            haces — esta acción no se puede deshacer.
                        </small>
                    </span>
                </label>
            </section>

            <footer className={styles.footer}>
                <Button
                    onClick={() => {
                        void save();
                    }}
                    disabled={update.isPending}
                >
                    {update.isPending ? 'Guardando…' : 'Guardar ajustes'}
                </Button>
                {feedback !== null && (
                    <span className={feedback === 'Ajustes guardados.' ? styles.ok : styles.err}>
                        {feedback}
                    </span>
                )}
            </footer>
        </div>
    );
}
