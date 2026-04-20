import { ProgressBar } from './components/ProgressBar';
import { useBookingStore } from './store/bookingStore';
import { Step1Aforo } from './steps/Step1Aforo';
import { Step2Sala } from './steps/Step2Sala';
import { Step3Fechas } from './steps/Step3Fechas';
import { Step4Recurrencia } from './steps/Step4Recurrencia';
import { Step5Horario } from './steps/Step5Horario';
import { Step6Perfil } from './steps/Step6Perfil';
import { Step7Resumen } from './steps/Step7Resumen';
import { Step8Exito } from './steps/Step8Exito';

const STEP_LABELS = [
    'Aforo',
    'Sala',
    'Fechas',
    'Recurrencia',
    'Horario',
    'Datos',
    'Resumen',
    'Éxito',
] as const;

export function App(): JSX.Element {
    const step = useBookingStore((s) => s.currentStep);

    return (
        <div className="reservas-app-root">
            <ProgressBar current={step} total={STEP_LABELS.length} labels={STEP_LABELS} />
            {step === 1 && <Step1Aforo />}
            {step === 2 && <Step2Sala />}
            {step === 3 && <Step3Fechas />}
            {step === 4 && <Step4Recurrencia />}
            {step === 5 && <Step5Horario />}
            {step === 6 && <Step6Perfil />}
            {step === 7 && <Step7Resumen />}
            {step === 8 && <Step8Exito />}
        </div>
    );
}
