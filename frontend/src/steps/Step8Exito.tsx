import { Button } from '../components/Button';
import { StepFrame } from '../components/StepFrame';
import { useBookingStore } from '../store/bookingStore';

import styles from './Step8Exito.module.css';

function icalUrlFor(uuid: string): string {
    const base = window.ReservasAldealab?.restBase ?? '/wp-json/reservas/v1';
    return `${base.replace(/\/$/, '')}/bookings/${uuid}/ical`;
}

export function Step8Exito(): JSX.Element {
    const booking = useBookingStore((s) => s.confirmedBooking);
    const reset = useBookingStore((s) => s.reset);

    return (
        <StepFrame
            title="¡Reserva recibida!"
            subtitle="Te hemos enviado un email de confirmación con los próximos pasos."
            actions={
                <>
                    <span />
                    <Button variant="secondary" onClick={reset}>
                        Hacer otra reserva
                    </Button>
                </>
            }
        >
            {booking !== null && (
                <div className={styles.card}>
                    <div className={styles.row}>
                        <span className={styles.label}>Número de reserva</span>
                        <code className={styles.code}>#{booking.id}</code>
                    </div>
                    <div className={styles.row}>
                        <span className={styles.label}>Referencia</span>
                        <code className={styles.code}>{booking.uuid}</code>
                    </div>
                    <p className={styles.note}>
                        Si tu reserva requiere presentar solicitud en la Sede Electrónica del
                        Ayuntamiento de Cáceres, encontrarás el PDF adjunto en el correo y las
                        instrucciones en el propio email.
                    </p>
                    <div className={styles.actions}>
                        <a
                            href={icalUrlFor(booking.uuid)}
                            download={`reserva-${booking.uuid}.ics`}
                            className={styles.icalLink}
                        >
                            Añadir al calendario (.ics)
                        </a>
                    </div>
                </div>
            )}
            {booking === null && <p>Gracias. Puedes cerrar esta ventana.</p>}
        </StepFrame>
    );
}
