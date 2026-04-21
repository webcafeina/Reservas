import { useMemo, useState } from 'react';

import { Button } from '../components/Button';
import { ErrorMessage } from '../components/ErrorMessage';
import { StepFrame } from '../components/StepFrame';
import { TurnstileWidget } from '../components/TurnstileWidget';
import { useCreateBooking } from '../api/bookings';
import { useSpace } from '../api/spaces';
import { ApiError } from '../api/client';
import { useBookingStore } from '../store/bookingStore';
import { buildRrule } from '../store/buildRrule';

import styles from './Step7Resumen.module.css';

const DATE_FORMATTER_ES = new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

/** Convert a YYYY-MM-DD string to "21 de abril de 2026". Empty string in → '—'. */
function formatDateEs(iso: string | null | undefined): string {
    if (iso === null || iso === undefined || iso === '') return '—';
    // Force UTC to avoid the off-by-one day caused by local-tz parsing of
    // a bare YYYY-MM-DD literal.
    const date = new Date(iso + 'T00:00:00Z');
    if (Number.isNaN(date.getTime())) return iso;
    return DATE_FORMATTER_ES.format(date);
}

export function Step7Resumen(): JSX.Element {
    const state = useBookingStore();
    const goBack = useBookingStore((s) => s.goBack);
    const setConfirmedBooking = useBookingStore((s) => s.setConfirmedBooking);
    const setStep = useBookingStore((s) => s.setStep);
    const setTurnstileToken = useBookingStore((s) => s.setTurnstileToken);

    const { data: sala } = useSpace(state.selectedSalaId);
    const createBooking = useCreateBooking();

    const [submitError, setSubmitError] = useState<string | null>(null);

    const rrule = useMemo<string | null>(() => {
        if (state.dateMode !== 'recurring') return null;
        try {
            return buildRrule(state.rruleInput);
        } catch {
            return null;
        }
    }, [state.dateMode, state.rruleInput]);

    const turnstileSiteKey = window.ReservasAldealab?.turnstileSiteKey ?? '';
    const requiresTurnstile = turnstileSiteKey !== '';
    const canSubmit =
        (!requiresTurnstile || state.turnstileToken !== null) && !createBooking.isPending;

    const handleSubmit = async (): Promise<void> => {
        setSubmitError(null);
        try {
            const result = await createBooking.mutateAsync({
                sala_id: state.selectedSalaId ?? 0,
                hora_inicio: state.horaInicio,
                hora_fin: state.horaFin,
                fecha_inicio: state.fechaInicio,
                fecha_fin_serie: state.dateMode === 'recurring' ? state.fechaFinSerie : null,
                rrule,
                fechas_excluidas: state.fechasExcluidas,
                objeto_reserva: state.objetoReserva,
                profile: state.profile,
                turnstile_token: state.turnstileToken,
            });
            setConfirmedBooking(result.booking);
            setStep(8);
        } catch (err) {
            if (err instanceof ApiError) {
                setSubmitError(err.message);
            } else if (err instanceof Error) {
                setSubmitError(err.message);
            } else {
                setSubmitError('Error desconocido al crear la reserva.');
            }
        }
    };

    return (
        <StepFrame
            title="Resumen y confirmación"
            subtitle="Revisa los datos. Al confirmar, crearemos la reserva y te enviaremos un email."
            actions={
                <>
                    <Button variant="ghost" onClick={goBack}>
                        Atrás
                    </Button>
                    <Button
                        onClick={() => {
                            void handleSubmit();
                        }}
                        disabled={!canSubmit}
                    >
                        {createBooking.isPending ? 'Enviando…' : 'Confirmar reserva'}
                    </Button>
                </>
            }
        >
            <dl className={styles.summary}>
                <div>
                    <dt>Sala</dt>
                    <dd>{sala !== undefined ? sala.title : '—'}</dd>
                </div>
                <div>
                    <dt>Fecha de inicio</dt>
                    <dd>{formatDateEs(state.fechaInicio)}</dd>
                </div>
                {state.dateMode === 'recurring' && (
                    <>
                        <div>
                            <dt>Recurrencia</dt>
                            <dd>{rrule ?? 'Configuración incompleta'}</dd>
                        </div>
                        <div>
                            <dt>Fin de la serie</dt>
                            <dd>
                                {state.fechaFinSerie !== null && state.fechaFinSerie !== ''
                                    ? formatDateEs(state.fechaFinSerie)
                                    : 'Según reglas'}
                            </dd>
                        </div>
                        {state.fechasExcluidas.length > 0 && (
                            <div>
                                <dt>Fechas excluidas</dt>
                                <dd>{state.fechasExcluidas.map(formatDateEs).join(', ')}</dd>
                            </div>
                        )}
                    </>
                )}
                <div>
                    <dt>Horario</dt>
                    <dd>
                        {state.horaInicio} – {state.horaFin}
                    </dd>
                </div>
                <div>
                    <dt>Solicitante</dt>
                    <dd>
                        {state.profile.nombre} {state.profile.primer_apellido}{' '}
                        {state.profile.segundo_apellido ?? ''}
                        <br />
                        <small>{state.profile.email}</small>
                    </dd>
                </div>
                <div>
                    <dt>Objeto</dt>
                    <dd>{state.objetoReserva}</dd>
                </div>
            </dl>

            {requiresTurnstile && (
                <div className={styles.turnstileSlot}>
                    <TurnstileWidget
                        siteKey={turnstileSiteKey}
                        onVerify={setTurnstileToken}
                        onExpire={() => setTurnstileToken(null)}
                        onError={() => setTurnstileToken(null)}
                    />
                </div>
            )}

            {submitError !== null && (
                <ErrorMessage title="No se pudo crear la reserva" message={submitError} />
            )}
        </StepFrame>
    );
}
