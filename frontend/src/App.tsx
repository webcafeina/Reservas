import { useEffect } from 'react';

import { ProgressBar } from './components/ProgressBar';
import { WebcafeinaFooter } from './components/WebcafeinaFooter';
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

    /*
     * Enter advances to the next step on Steps 1–6. Each "Siguiente"
     * button opts in via `data-step-advance`; the Confirmar reserva
     * button on Step 7 deliberately does NOT, so the user has to click
     * to commit the booking. Step 8 is the success screen.
     *
     * We also skip:
     *   - non-plain Enter (Shift/Ctrl/Meta/Alt) — modifier shortcuts.
     *   - Enter inside a <textarea> (multi-line input).
     *   - Enter on a <button> or <a> (those handle their own activation).
     *   - Enter inside contentEditable.
     */
    useEffect(() => {
        if (step < 1 || step > 6) {
            return;
        }
        const handler = (e: KeyboardEvent): void => {
            if (e.key !== 'Enter') return;
            if (e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) return;

            const target = e.target;
            if (target instanceof HTMLElement) {
                const tag = target.tagName.toLowerCase();
                if (tag === 'textarea' || tag === 'button' || tag === 'a') return;
                if (target.isContentEditable) return;
            }

            const btn = document.querySelector<HTMLButtonElement>(
                '[data-step-advance]:not([disabled])',
            );
            if (btn !== null) {
                e.preventDefault();
                btn.click();
            }
        };
        document.addEventListener('keydown', handler);
        return () => {
            document.removeEventListener('keydown', handler);
        };
    }, [step]);

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
            <WebcafeinaFooter />
        </div>
    );
}
