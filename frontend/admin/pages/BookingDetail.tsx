import { useEffect, useState } from 'react';

import { Button } from '../../src/components/Button';
import { SelectField, TextareaField } from '../../src/components/Field';
import { ErrorMessage } from '../../src/components/ErrorMessage';
import { useAdminBooking, useDeleteBooking, useUpdateBooking } from '../api/hooks';
import { navigate } from '../useHashRoute';
import type { BookingState } from '../../src/types/booking';
import type { UserProfile } from '../../src/types/profile';
import { formatDateEs, formatDateTimeEs } from '../../src/utils/dateFormat';

import styles from './BookingDetail.module.css';

interface BookingDetailProps {
    id: number;
}

const STATES: Array<{ value: BookingState; label: string }> = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'confirmada', label: 'Confirmada' },
    { value: 'cancelada', label: 'Cancelada (dispara email al usuario)' },
    { value: 'finalizada', label: 'Finalizada' },
];

export function BookingDetail({ id }: BookingDetailProps): JSX.Element {
    const { data, isLoading, isError } = useAdminBooking(id);
    const update = useUpdateBooking();
    const del = useDeleteBooking();

    const [estado, setEstado] = useState<BookingState>('pendiente');
    const [nota, setNota] = useState('');
    const [feedback, setFeedback] = useState<string | null>(null);

    useEffect(() => {
        if (data !== undefined) {
            setEstado(data.estado);
            setNota(data.nota_admin ?? '');
        }
    }, [data]);

    if (isLoading) return <p>Cargando…</p>;
    if (isError || data === undefined) return <ErrorMessage message="Reserva no encontrada." />;

    const save = async (): Promise<void> => {
        setFeedback(null);
        try {
            await update.mutateAsync({ id, payload: { estado, nota_admin: nota } });
            setFeedback('Cambios guardados.');
        } catch {
            setFeedback('Error al guardar.');
        }
    };

    const remove = async (): Promise<void> => {
        if (!window.confirm('¿Eliminar esta reserva definitivamente?')) return;
        await del.mutateAsync(id);
        navigate('bookings');
    };

    return (
        <div className={styles.wrapper}>
            <header className={styles.header}>
                <Button variant="ghost" onClick={() => navigate('bookings')}>
                    ← Volver al listado
                </Button>
                <h2>
                    Reserva #{data.id}
                    <small className={styles.uuid}>{data.uuid}</small>
                </h2>
            </header>

            <section className={styles.grid}>
                <article className={styles.card}>
                    <h3>Detalles</h3>
                    <dl>
                        <div>
                            <dt>Sala</dt>
                            <dd>
                                {data.sala_title !== null && data.sala_title !== undefined
                                    ? `${data.sala_title} (#${data.sala_id})`
                                    : `#${data.sala_id}`}
                            </dd>
                        </div>
                        <div>
                            <dt>Fecha inicio</dt>
                            <dd>{formatDateEs(data.fecha_inicio)}</dd>
                        </div>
                        <div>
                            <dt>Fin de serie</dt>
                            <dd>{formatDateEs(data.fecha_fin_serie)}</dd>
                        </div>
                        <div>
                            <dt>Recurrencia</dt>
                            <dd>
                                <code>{data.rrule ?? 'No recurrente'}</code>
                            </dd>
                        </div>
                        <div>
                            <dt>Horario</dt>
                            <dd>
                                {data.hora_inicio.slice(0, 5)} – {data.hora_fin.slice(0, 5)}
                            </dd>
                        </div>
                        <div>
                            <dt>Objeto</dt>
                            <dd>{data.objeto_reserva}</dd>
                        </div>
                        <div>
                            <dt>Creada</dt>
                            <dd>{formatDateTimeEs(data.created_at)}</dd>
                        </div>
                        <div>
                            <dt>Fechas expandidas</dt>
                            <dd>
                                {data.fechas.length === 0
                                    ? '—'
                                    : data.fechas.length > 6
                                      ? `${data.fechas.slice(0, 6).map(formatDateEs).join(', ')} (+${data.fechas.length - 6} más)`
                                      : data.fechas.map(formatDateEs).join(', ')}
                            </dd>
                        </div>
                    </dl>
                </article>

                {data.profile !== null && data.profile !== undefined && (
                    <SolicitanteCard profile={data.profile} />
                )}

                <article className={styles.card}>
                    <h3>Gestión</h3>
                    <SelectField
                        label="Estado"
                        value={estado}
                        onChange={(e) => setEstado(e.target.value as BookingState)}
                        options={STATES}
                    />
                    <TextareaField
                        label="Nota interna"
                        value={nota}
                        onChange={(e) => setNota(e.target.value)}
                        rows={4}
                        hint="Solo visible para gestores. No se envía al usuario."
                    />
                    <div className={styles.actions}>
                        <Button
                            onClick={() => {
                                void save();
                            }}
                            disabled={update.isPending}
                        >
                            {update.isPending ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() => {
                                void remove();
                            }}
                            disabled={del.isPending}
                        >
                            Eliminar
                        </Button>
                    </div>
                    {feedback !== null && <p className={styles.feedback}>{feedback}</p>}
                </article>
            </section>
        </div>
    );
}

function SolicitanteCard({ profile }: { profile: UserProfile }): JSX.Element {
    const fullName = [profile.nombre, profile.primer_apellido, profile.segundo_apellido]
        .filter((s) => s !== null && s !== '')
        .join(' ');
    const direccion = [
        profile.via,
        profile.numero,
        profile.letra,
        profile.escalera !== null && profile.escalera !== '' ? `Esc. ${profile.escalera}` : null,
        profile.piso !== null && profile.piso !== '' ? `Piso ${profile.piso}` : null,
        profile.puerta !== null && profile.puerta !== '' ? `Pta. ${profile.puerta}` : null,
    ]
        .filter((s) => s !== null && s !== '')
        .join(' ');
    const localidad = [profile.codigo_postal, profile.municipio, profile.provincia]
        .filter((s) => s !== null && s !== '')
        .join(', ');

    return (
        <article className={styles.card}>
            <h3>Datos del solicitante</h3>
            <dl>
                <div>
                    <dt>Nombre</dt>
                    <dd>{fullName !== '' ? fullName : '—'}</dd>
                </div>
                <div>
                    <dt>NIF</dt>
                    <dd>{profile.nif !== '' ? profile.nif : '—'}</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd>
                        {profile.email !== '' ? (
                            <a href={`mailto:${profile.email}`}>{profile.email}</a>
                        ) : (
                            '—'
                        )}
                    </dd>
                </div>
                <div>
                    <dt>Móvil</dt>
                    <dd>
                        {profile.movil !== '' ? (
                            <a href={`tel:${profile.movil}`}>{profile.movil}</a>
                        ) : (
                            '—'
                        )}
                    </dd>
                </div>
                {profile.telefono_fijo !== null && profile.telefono_fijo !== '' && (
                    <div>
                        <dt>Teléfono fijo</dt>
                        <dd>{profile.telefono_fijo}</dd>
                    </div>
                )}
                <div>
                    <dt>Dirección</dt>
                    <dd>{direccion !== '' ? direccion : '—'}</dd>
                </div>
                <div>
                    <dt>Localidad</dt>
                    <dd>{localidad !== '' ? localidad : '—'}</dd>
                </div>
            </dl>
        </article>
    );
}
