import { Button } from '../components/Button';
import { ErrorMessage } from '../components/ErrorMessage';
import { SalaCard } from '../components/SalaCard';
import { StepFrame } from '../components/StepFrame';
import { useSpaces } from '../api/spaces';
import { useBookingStore } from '../store/bookingStore';

import styles from './Step2Sala.module.css';

export function Step2Sala(): JSX.Element {
    const criteria = useBookingStore((s) => s.criteria);
    const selectedSalaId = useBookingStore((s) => s.selectedSalaId);
    const setSelectedSala = useBookingStore((s) => s.setSelectedSala);
    const goNext = useBookingStore((s) => s.goNext);
    const goBack = useBookingStore((s) => s.goBack);

    const { data, isLoading, isError } = useSpaces({
        aforo_min: criteria.aforoMin ?? undefined,
        aforo_max: criteria.aforoMax ?? undefined,
        servicios: criteria.servicios.length > 0 ? criteria.servicios : undefined,
        disponible: true,
        per_page: 100,
    });

    return (
        <StepFrame
            title="Elige la sala"
            subtitle="Selecciona el espacio que mejor encaje con tu reserva."
            actions={
                <>
                    <Button variant="ghost" onClick={goBack}>
                        Atrás
                    </Button>
                    <Button onClick={goNext} disabled={selectedSalaId === null}>
                        Siguiente
                    </Button>
                </>
            }
        >
            {isLoading && <p>Cargando salas…</p>}
            {isError && (
                <ErrorMessage
                    title="No se pudieron cargar las salas"
                    message="Recarga la página. Si el problema persiste, contacta con el gestor."
                />
            )}
            {data !== undefined && data.items.length === 0 && (
                <ErrorMessage
                    title="No hay salas disponibles"
                    message="Ningún espacio coincide con tus criterios. Prueba a relajarlos en el paso anterior."
                />
            )}
            {data !== undefined && data.items.length > 0 && (
                <div className={styles.grid}>
                    {data.items.map((sala) => (
                        <SalaCard
                            key={sala.id}
                            sala={sala}
                            selected={sala.id === selectedSalaId}
                            onSelect={setSelectedSala}
                        />
                    ))}
                </div>
            )}
        </StepFrame>
    );
}
