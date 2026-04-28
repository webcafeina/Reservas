import { useEffect, useMemo, useRef, useState } from 'react';

import { Button } from '../../src/components/Button';
import { ErrorMessage } from '../../src/components/ErrorMessage';
import { TextField, SelectField, TextareaField } from '../../src/components/Field';
import { OccurrenceCalendar } from '../../src/components/OccurrenceCalendar';
import { SalaCard } from '../../src/components/SalaCard';
import { useSpaces } from '../../src/api/spaces';
import { ApiError } from '../../src/api/client';
import { isValidProfile, validateProfile } from '../../src/hooks/profileValidation';
import { buildRrule, type Freq, type RruleInput, type Weekday } from '../../src/store/buildRrule';
import { parseRrule } from '../../src/store/humanizeRrule';
import { expandOccurrences } from '../../src/store/expandOccurrences';
import { emptyProfile, type UserProfile } from '../../src/types/profile';
import type { BookingState } from '../../src/types/booking';

import {
    useAdminBooking,
    useCreateAdminBooking,
    useUpdateAdminBooking,
    type AdminBookingPayload,
} from '../api/hooks';
import { navigate } from '../useHashRoute';

import styles from './BookingNew.module.css';

const WEEKDAYS: Array<{ id: Weekday; label: string }> = [
    { id: 'MO', label: 'L' },
    { id: 'TU', label: 'M' },
    { id: 'WE', label: 'X' },
    { id: 'TH', label: 'J' },
    { id: 'FR', label: 'V' },
    { id: 'SA', label: 'S' },
    { id: 'SU', label: 'D' },
];

const STATE_OPTIONS: Array<{ value: BookingState; label: string }> = [
    { value: 'confirmada', label: 'Confirmada' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'cancelada', label: 'Cancelada' },
];

function defaultRrule(): RruleInput {
    return {
        freq: 'WEEKLY',
        interval: 1,
        byweekday: [],
        end: { kind: 'count', count: 4 },
    };
}

interface BookingNewProps {
    /**
     * If set, the form switches to "edit existing booking" mode: it
     * fetches the booking, pre-fills every field, calls PUT instead of
     * POST on submit, and tweaks the headline + button label. Omit for
     * the default "create new" mode.
     */
    editingId?: number;
}

export function BookingNew({ editingId }: BookingNewProps = {}): JSX.Element {
    const isEdit = editingId !== undefined && editingId > 0;

    // — Sala —
    const { data: spaces, isLoading: loadingSpaces } = useSpaces({
        disponible: true,
        per_page: 100,
    });
    const [salaId, setSalaId] = useState<number | null>(null);

    // — Fechas y horario —
    const [fechaInicio, setFechaInicio] = useState('');
    const [horaInicio, setHoraInicio] = useState('09:00');
    const [horaFin, setHoraFin] = useState('10:00');
    const [recurring, setRecurring] = useState(false);
    const [rrule, setRrule] = useState<RruleInput>(defaultRrule);
    const [fechasExcluidas, setFechasExcluidas] = useState<string[]>([]);

    // — Contenido —
    const [objeto, setObjeto] = useState('');

    // — Perfil del solicitante —
    const [profile, setProfile] = useState<UserProfile>(emptyProfile);
    const patchProfile = (p: Partial<UserProfile>): void =>
        setProfile((prev) => ({ ...prev, ...p }));

    // — Opciones admin —
    const [estado, setEstado] = useState<BookingState>('confirmada');
    const [forceOverride, setForceOverride] = useState(false);
    const [suppressNotifications, setSuppressNotifications] = useState(false);
    const [notaAdmin, setNotaAdmin] = useState('');

    // — Submit —
    const createBooking = useCreateAdminBooking();
    const updateBooking = useUpdateAdminBooking();
    const [submitError, setSubmitError] = useState<string | null>(null);

    // — Edit-mode pre-fill —
    const editingBooking = useAdminBooking(isEdit ? editingId : null);
    const prefilledRef = useRef<number | null>(null);

    useEffect(() => {
        if (!isEdit) return;
        const data = editingBooking.data;
        if (data === undefined) return;
        if (prefilledRef.current === data.id) return;
        prefilledRef.current = data.id;

        setSalaId(data.sala_id);
        setFechaInicio(data.fecha_inicio);
        setHoraInicio(data.hora_inicio.slice(0, 5));
        setHoraFin(data.hora_fin.slice(0, 5));
        setObjeto(data.objeto_reserva);
        setEstado(data.estado);
        setNotaAdmin(data.nota_admin ?? '');
        if (data.profile !== null && data.profile !== undefined) {
            setProfile(data.profile);
        }
        if (data.rrule !== null && data.rrule !== '') {
            setRecurring(true);
            setRrule(parseRrule(data.rrule));
            setFechasExcluidas(data.fechas_excluidas ?? []);
        } else {
            setRecurring(false);
            setRrule(defaultRrule());
            setFechasExcluidas([]);
        }
    }, [isEdit, editingBooking.data]);

    const profileErrors = validateProfile(profile);
    const profileValid = isValidProfile(profile);

    const occurrences = useMemo<Date[]>(() => {
        if (!recurring || fechaInicio === '') return [];
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(fechaInicio);
        if (!m) return [];
        const start = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        try {
            return expandOccurrences(rrule, start);
        } catch {
            return [];
        }
    }, [recurring, rrule, fechaInicio]);

    const submitting = createBooking.isPending || updateBooking.isPending;
    const canSubmit =
        salaId !== null &&
        fechaInicio !== '' &&
        horaInicio !== '' &&
        horaFin !== '' &&
        horaInicio < horaFin &&
        objeto.trim() !== '' &&
        profileValid &&
        !submitting;

    const toggleWeekday = (wd: Weekday): void => {
        const current = rrule.byweekday ?? [];
        const next = current.includes(wd) ? current.filter((x) => x !== wd) : [...current, wd];
        setRrule((prev) => ({ ...prev, byweekday: next }));
    };

    const toggleExclusion = (iso: string): void => {
        setFechasExcluidas((prev) =>
            prev.includes(iso) ? prev.filter((x) => x !== iso) : [...prev, iso],
        );
    };

    const handleSubmit = async (): Promise<void> => {
        setSubmitError(null);
        if (salaId === null) return;

        // For recurring bookings, build the RRULE string + end boundary.
        let rruleString: string | null = null;
        let fechaFinSerie: string | null = null;
        if (recurring) {
            try {
                rruleString = buildRrule(rrule);
            } catch (err) {
                setSubmitError(err instanceof Error ? err.message : 'Recurrencia no válida.');
                return;
            }
            if (rrule.end.kind === 'until' && rrule.end.date !== '') {
                fechaFinSerie = rrule.end.date;
            }
        }

        const payload: AdminBookingPayload = {
            sala_id: salaId,
            hora_inicio: horaInicio,
            hora_fin: horaFin,
            fecha_inicio: fechaInicio,
            fecha_fin_serie: fechaFinSerie,
            rrule: rruleString,
            fechas_excluidas: recurring ? fechasExcluidas : [],
            objeto_reserva: objeto,
            profile,
            estado,
            force_override: forceOverride,
            suppress_notifications: suppressNotifications,
            ...(notaAdmin.trim() !== '' ? { nota_admin: notaAdmin } : {}),
        };

        try {
            if (isEdit && editingId !== undefined) {
                // For edits, the backend reads `notify_user` (default true)
                // rather than the inverse `suppress_notifications`. Send the
                // explicit value so the toggle behaves the same as the rest
                // of the admin UI.
                const updatePayload = {
                    ...payload,
                    notify_user: !suppressNotifications,
                };
                const result = await updateBooking.mutateAsync({
                    id: editingId,
                    payload: updatePayload,
                });
                navigate(`bookings/${result.booking.id}`);
            } else {
                const result = await createBooking.mutateAsync(payload);
                navigate(`bookings/${result.booking.id}`);
            }
        } catch (err) {
            if (err instanceof ApiError) {
                setSubmitError(err.message);
            } else if (err instanceof Error) {
                setSubmitError(err.message);
            } else {
                setSubmitError(
                    isEdit
                        ? 'Error desconocido al guardar los cambios.'
                        : 'Error desconocido al crear la reserva.',
                );
            }
        }
    };

    if (isEdit && editingBooking.isLoading) {
        return <p>Cargando reserva…</p>;
    }
    if (isEdit && (editingBooking.isError || editingBooking.data === undefined)) {
        return <ErrorMessage message="No se pudo cargar la reserva a editar." />;
    }

    const heading = isEdit ? `Editar reserva #${editingId ?? ''}` : 'Crear reserva manual';
    const submitLabel = (() => {
        if (submitting) {
            return isEdit ? 'Guardando…' : 'Creando…';
        }
        return isEdit ? 'Guardar cambios' : 'Crear reserva';
    })();
    const cancelTarget = isEdit && editingId !== undefined ? `bookings/${editingId}` : 'bookings';

    return (
        <div className={styles.wrapper}>
            <header className={styles.header}>
                <h2>{heading}</h2>
                <Button variant="ghost" onClick={() => navigate(cancelTarget)}>
                    ← Cancelar
                </Button>
            </header>

            {/* —————————— Sala —————————— */}
            <section className={styles.section}>
                <h3>Sala</h3>
                {loadingSpaces && <p className={styles.muted}>Cargando salas…</p>}
                {spaces !== undefined && spaces.items.length === 0 && (
                    <p className={styles.muted}>No hay salas reservables configuradas.</p>
                )}
                {spaces !== undefined && spaces.items.length > 0 && (
                    <div className={styles.salaGrid}>
                        {spaces.items.map((sala) => (
                            <SalaCard
                                key={sala.id}
                                sala={sala}
                                selected={sala.id === salaId}
                                onSelect={setSalaId}
                            />
                        ))}
                    </div>
                )}
            </section>

            {/* —————————— Fechas & horario —————————— */}
            <section className={styles.section}>
                <h3>Fechas y horario</h3>
                <div className={styles.row}>
                    <TextField
                        label="Fecha de inicio"
                        type="date"
                        value={fechaInicio}
                        onChange={(e) => setFechaInicio(e.target.value)}
                        required
                    />
                    <TextField
                        label="Hora de inicio"
                        type="time"
                        value={horaInicio}
                        onChange={(e) => setHoraInicio(e.target.value)}
                        required
                    />
                    <TextField
                        label="Hora de fin"
                        type="time"
                        value={horaFin}
                        onChange={(e) => setHoraFin(e.target.value)}
                        required
                        error={
                            horaInicio >= horaFin
                                ? 'La hora de fin debe ser posterior a la de inicio.'
                                : null
                        }
                    />
                </div>

                <label className={styles.checkbox}>
                    <input
                        type="checkbox"
                        checked={recurring}
                        onChange={(e) => setRecurring(e.target.checked)}
                    />
                    <span>
                        <strong>Reserva recurrente</strong>
                        <small>Activa para configurar frecuencia, fin y exclusiones.</small>
                    </span>
                </label>

                {recurring && (
                    <>
                        <div className={styles.row}>
                            <SelectField
                                label="Frecuencia"
                                value={rrule.freq}
                                onChange={(e) =>
                                    setRrule((prev) => ({ ...prev, freq: e.target.value as Freq }))
                                }
                                options={[
                                    { value: 'DAILY', label: 'Diaria' },
                                    { value: 'WEEKLY', label: 'Semanal' },
                                    { value: 'MONTHLY', label: 'Mensual' },
                                    { value: 'YEARLY', label: 'Anual' },
                                ]}
                            />
                            <TextField
                                label="Cada"
                                type="number"
                                min={1}
                                step={1}
                                value={rrule.interval}
                                onChange={(e) =>
                                    setRrule((prev) => ({
                                        ...prev,
                                        interval: Math.max(1, Number(e.target.value) || 1),
                                    }))
                                }
                            />
                        </div>

                        {rrule.freq === 'WEEKLY' && (
                            <div>
                                <label className={styles.muted}>Días de la semana</label>
                                <div className={styles.weekdays}>
                                    {WEEKDAYS.map((wd) => {
                                        const active = (rrule.byweekday ?? []).includes(wd.id);
                                        return (
                                            <button
                                                type="button"
                                                key={wd.id}
                                                className={`${styles.weekday} ${active ? styles.weekdayActive : ''}`}
                                                onClick={() => toggleWeekday(wd.id)}
                                                aria-pressed={active}
                                            >
                                                {wd.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        <div className={styles.row}>
                            <SelectField
                                label="Finaliza"
                                value={rrule.end.kind}
                                onChange={(e) => {
                                    const kind = e.target.value as 'until' | 'count' | 'never';
                                    if (kind === 'until') {
                                        setRrule((prev) => ({
                                            ...prev,
                                            end: { kind: 'until', date: '' },
                                        }));
                                    } else if (kind === 'count') {
                                        setRrule((prev) => ({
                                            ...prev,
                                            end: { kind: 'count', count: 4 },
                                        }));
                                    } else {
                                        setRrule((prev) => ({ ...prev, end: { kind: 'never' } }));
                                    }
                                }}
                                options={[
                                    { value: 'until', label: 'Hasta una fecha' },
                                    { value: 'count', label: 'Un número de ocurrencias' },
                                    { value: 'never', label: 'Sin fin (limitado a 1 año)' },
                                ]}
                            />
                            {rrule.end.kind === 'until' && (
                                <TextField
                                    label="Hasta la fecha"
                                    type="date"
                                    value={rrule.end.date}
                                    onChange={(e) =>
                                        setRrule((prev) => ({
                                            ...prev,
                                            end: { kind: 'until', date: e.target.value },
                                        }))
                                    }
                                />
                            )}
                            {rrule.end.kind === 'count' && (
                                <TextField
                                    label="Ocurrencias"
                                    type="number"
                                    min={1}
                                    step={1}
                                    value={rrule.end.count}
                                    onChange={(e) =>
                                        setRrule((prev) => ({
                                            ...prev,
                                            end: {
                                                kind: 'count',
                                                count: Math.max(1, Number(e.target.value) || 1),
                                            },
                                        }))
                                    }
                                />
                            )}
                        </div>

                        {occurrences.length > 0 && (
                            <div>
                                <label className={styles.muted}>
                                    Vista previa (haz clic para excluir una ocurrencia)
                                </label>
                                <OccurrenceCalendar
                                    occurrences={occurrences}
                                    excluded={fechasExcluidas}
                                    onToggleExclude={toggleExclusion}
                                />
                            </div>
                        )}
                    </>
                )}
            </section>

            {/* —————————— Objeto —————————— */}
            <section className={styles.section}>
                <h3>Motivo de la reserva</h3>
                <TextareaField
                    label="Objeto de la reserva"
                    value={objeto}
                    onChange={(e) => setObjeto(e.target.value)}
                    rows={3}
                    required
                    hint="Para qué va a usarse el espacio."
                />
            </section>

            {/* —————————— Perfil —————————— */}
            <section className={styles.section}>
                <h3>Datos del solicitante</h3>
                <div className={styles.profileGrid}>
                    <TextField
                        label="NIF"
                        value={profile.nif}
                        onChange={(e) => patchProfile({ nif: e.target.value })}
                        required
                        error={profileErrors.nif ?? null}
                    />
                    <TextField
                        label="Nombre"
                        value={profile.nombre}
                        onChange={(e) => patchProfile({ nombre: e.target.value })}
                        required
                        error={profileErrors.nombre ?? null}
                    />
                    <TextField
                        label="Primer apellido"
                        value={profile.primer_apellido}
                        onChange={(e) => patchProfile({ primer_apellido: e.target.value })}
                        required
                        error={profileErrors.primer_apellido ?? null}
                    />
                    <TextField
                        label="Segundo apellido"
                        value={profile.segundo_apellido ?? ''}
                        onChange={(e) =>
                            patchProfile({
                                segundo_apellido: e.target.value === '' ? null : e.target.value,
                            })
                        }
                    />
                    <TextField
                        label="Email"
                        type="email"
                        value={profile.email}
                        onChange={(e) => patchProfile({ email: e.target.value })}
                        required
                        error={profileErrors.email ?? null}
                    />
                    <TextField
                        label="Móvil"
                        value={profile.movil}
                        onChange={(e) => patchProfile({ movil: e.target.value })}
                        required
                        error={profileErrors.movil ?? null}
                    />
                    <TextField
                        label="Teléfono fijo"
                        value={profile.telefono_fijo ?? ''}
                        onChange={(e) =>
                            patchProfile({
                                telefono_fijo: e.target.value === '' ? null : e.target.value,
                            })
                        }
                    />
                    <TextField
                        label="Empresa"
                        hint="Opcional"
                        value={profile.empresa ?? ''}
                        onChange={(e) =>
                            patchProfile({
                                empresa: e.target.value === '' ? null : e.target.value,
                            })
                        }
                    />
                    <TextField
                        label="Calle / Plaza / Avenida"
                        value={profile.via}
                        onChange={(e) => patchProfile({ via: e.target.value })}
                        required
                        error={profileErrors.via ?? null}
                    />
                    <TextField
                        label="Número"
                        value={profile.numero}
                        onChange={(e) => patchProfile({ numero: e.target.value })}
                        required
                        error={profileErrors.numero ?? null}
                    />
                    <TextField
                        label="Municipio"
                        value={profile.municipio}
                        onChange={(e) => patchProfile({ municipio: e.target.value })}
                        required
                        error={profileErrors.municipio ?? null}
                    />
                    <TextField
                        label="Provincia"
                        value={profile.provincia}
                        onChange={(e) => patchProfile({ provincia: e.target.value })}
                        required
                        error={profileErrors.provincia ?? null}
                    />
                    <TextField
                        label="Código postal"
                        value={profile.codigo_postal}
                        onChange={(e) => patchProfile({ codigo_postal: e.target.value })}
                        required
                        error={profileErrors.codigo_postal ?? null}
                    />
                </div>
            </section>

            {/* —————————— Opciones admin —————————— */}
            <section className={styles.section}>
                <h3>Opciones del administrador</h3>
                <div className={styles.row}>
                    <SelectField
                        label="Estado inicial"
                        value={estado}
                        onChange={(e) => setEstado(e.target.value as BookingState)}
                        options={STATE_OPTIONS}
                    />
                </div>
                <div className={styles.checkboxRow}>
                    <label className={styles.checkbox}>
                        <input
                            type="checkbox"
                            checked={forceOverride}
                            onChange={(e) => setForceOverride(e.target.checked)}
                        />
                        <span>
                            <strong>Forzar aunque haya solapamiento</strong>
                            <small>
                                Salta la comprobación de conflictos con otras reservas. Úsalo sólo
                                si sabes que el solapamiento es intencionado.
                            </small>
                        </span>
                    </label>
                    <label className={styles.checkbox}>
                        <input
                            type="checkbox"
                            checked={suppressNotifications}
                            onChange={(e) => setSuppressNotifications(e.target.checked)}
                        />
                        <span>
                            <strong>No notificar por email</strong>
                            <small>
                                No envía email al usuario ni a los administradores. Útil si ya se ha
                                informado al solicitante por otra vía.
                            </small>
                        </span>
                    </label>
                </div>
                <TextareaField
                    label="Nota interna del admin"
                    value={notaAdmin}
                    onChange={(e) => setNotaAdmin(e.target.value)}
                    rows={2}
                    hint="Queda guardada en la reserva. Sólo visible para administradores."
                />
            </section>

            {submitError !== null && (
                <ErrorMessage
                    title={isEdit ? 'No se pudo guardar' : 'No se pudo crear la reserva'}
                    message={submitError}
                />
            )}

            <footer className={styles.actions}>
                <Button variant="ghost" onClick={() => navigate(cancelTarget)}>
                    ← Cancelar
                </Button>
                <Button
                    onClick={() => {
                        void handleSubmit();
                    }}
                    disabled={!canSubmit}
                >
                    {submitLabel}
                </Button>
            </footer>
        </div>
    );
}
