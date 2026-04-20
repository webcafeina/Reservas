import { useState } from 'react';

import { Button } from '../components/Button';
import { SelectField, TextField } from '../components/Field';
import { StepFrame } from '../components/StepFrame';
import { useBookingStore } from '../store/bookingStore';
import type { Freq, Weekday, MonthlyMode } from '../store/buildRrule';

import styles from './Step4Recurrencia.module.css';

const WEEKDAYS: Array<{ id: Weekday; label: string }> = [
    { id: 'MO', label: 'L' },
    { id: 'TU', label: 'M' },
    { id: 'WE', label: 'X' },
    { id: 'TH', label: 'J' },
    { id: 'FR', label: 'V' },
    { id: 'SA', label: 'S' },
    { id: 'SU', label: 'D' },
];

export function Step4Recurrencia(): JSX.Element {
    const rrule = useBookingStore((s) => s.rruleInput);
    const setRruleInput = useBookingStore((s) => s.setRruleInput);
    const fechasExcluidas = useBookingStore((s) => s.fechasExcluidas);
    const setFechasExcluidas = useBookingStore((s) => s.setFechasExcluidas);
    const goBack = useBookingStore((s) => s.goBack);
    const setStep = useBookingStore((s) => s.setStep);

    const [exclusion, setExclusion] = useState('');

    const toggleWeekday = (wd: Weekday): void => {
        const current = rrule.byweekday ?? [];
        const next = current.includes(wd)
            ? current.filter((x) => x !== wd)
            : [...current, wd];
        setRruleInput({ byweekday: next });
    };

    const addExclusion = (): void => {
        if (exclusion === '') return;
        if (!fechasExcluidas.includes(exclusion)) {
            setFechasExcluidas([...fechasExcluidas, exclusion]);
        }
        setExclusion('');
    };

    const removeExclusion = (d: string): void => {
        setFechasExcluidas(fechasExcluidas.filter((x) => x !== d));
    };

    const freqOptions = [
        { value: 'DAILY', label: 'Diaria' },
        { value: 'WEEKLY', label: 'Semanal' },
        { value: 'MONTHLY', label: 'Mensual' },
        { value: 'YEARLY', label: 'Anual' },
    ];

    const endOptions = [
        { value: 'until', label: 'Hasta una fecha' },
        { value: 'count', label: 'Un número de ocurrencias' },
        { value: 'never', label: 'Sin fin (limitado a 1 año)' },
    ];

    return (
        <StepFrame
            title="Configura la recurrencia"
            subtitle="Define el patrón, la duración y las fechas que debemos excluir."
            actions={
                <>
                    <Button variant="ghost" onClick={goBack}>
                        Atrás
                    </Button>
                    <Button onClick={() => setStep(5)}>Siguiente</Button>
                </>
            }
        >
            <div className={styles.row}>
                <SelectField
                    label="Frecuencia"
                    value={rrule.freq}
                    onChange={(e) => setRruleInput({ freq: e.target.value as Freq })}
                    options={freqOptions}
                />
                <TextField
                    label="Cada"
                    type="number"
                    min={1}
                    step={1}
                    value={rrule.interval}
                    onChange={(e) =>
                        setRruleInput({ interval: Math.max(1, Number(e.target.value) || 1) })
                    }
                    hint={freqToIntervalHint(rrule.freq)}
                />
            </div>

            {rrule.freq === 'WEEKLY' && (
                <div>
                    <label className={styles.groupLabel}>Días de la semana</label>
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

            {rrule.freq === 'MONTHLY' && <MonthlySelector rrule={rrule.monthly} onChange={(m) => setRruleInput({ monthly: m })} />}

            <div className={styles.row}>
                <SelectField
                    label="Finaliza"
                    value={rrule.end.kind}
                    onChange={(e) => {
                        const kind = e.target.value as 'until' | 'count' | 'never';
                        if (kind === 'until') {
                            setRruleInput({ end: { kind: 'until', date: '' } });
                        } else if (kind === 'count') {
                            setRruleInput({ end: { kind: 'count', count: 10 } });
                        } else {
                            setRruleInput({ end: { kind: 'never' } });
                        }
                    }}
                    options={endOptions}
                />
                {rrule.end.kind === 'until' && (
                    <TextField
                        label="Hasta la fecha"
                        type="date"
                        value={rrule.end.date}
                        onChange={(e) => setRruleInput({ end: { kind: 'until', date: e.target.value } })}
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
                            setRruleInput({
                                end: { kind: 'count', count: Math.max(1, Number(e.target.value) || 1) },
                            })
                        }
                    />
                )}
            </div>

            <div>
                <label className={styles.groupLabel}>Fechas a excluir (opcional)</label>
                <div className={styles.exclusionRow}>
                    <input
                        type="date"
                        value={exclusion}
                        onChange={(e) => setExclusion(e.target.value)}
                        className={styles.dateInput}
                        aria-label="Nueva fecha a excluir"
                    />
                    <Button variant="secondary" onClick={addExclusion} disabled={exclusion === ''}>
                        Añadir
                    </Button>
                </div>
                {fechasExcluidas.length > 0 && (
                    <ul className={styles.exclusionList}>
                        {fechasExcluidas.map((d) => (
                            <li key={d}>
                                {d}
                                <button
                                    type="button"
                                    onClick={() => removeExclusion(d)}
                                    aria-label={`Quitar ${d}`}
                                    className={styles.removeBtn}
                                >
                                    ×
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </StepFrame>
    );
}

function freqToIntervalHint(freq: Freq): string {
    switch (freq) {
        case 'DAILY':
            return 'Cada X días (1 = todos los días).';
        case 'WEEKLY':
            return 'Cada X semanas (1 = cada semana).';
        case 'MONTHLY':
            return 'Cada X meses (1 = cada mes).';
        case 'YEARLY':
            return 'Cada X años (1 = cada año).';
    }
}

interface MonthlySelectorProps {
    rrule: MonthlyMode | undefined;
    onChange: (m: MonthlyMode) => void;
}

function MonthlySelector({ rrule, onChange }: MonthlySelectorProps): JSX.Element {
    const mode = rrule?.mode ?? 'day-of-month';

    const modeOptions = [
        { value: 'day-of-month', label: 'Día fijo del mes' },
        { value: 'nth-weekday', label: 'Nº y día de la semana (p. ej. 3er lunes)' },
    ];

    const nthOptions = [
        { value: '1', label: 'Primer' },
        { value: '2', label: 'Segundo' },
        { value: '3', label: 'Tercer' },
        { value: '4', label: 'Cuarto' },
        { value: '-1', label: 'Último' },
    ];

    const weekdayOptions = [
        { value: 'MO', label: 'Lunes' },
        { value: 'TU', label: 'Martes' },
        { value: 'WE', label: 'Miércoles' },
        { value: 'TH', label: 'Jueves' },
        { value: 'FR', label: 'Viernes' },
        { value: 'SA', label: 'Sábado' },
        { value: 'SU', label: 'Domingo' },
    ];

    return (
        <div className={styles.row}>
            <SelectField
                label="Modo mensual"
                value={mode}
                onChange={(e) =>
                    e.target.value === 'day-of-month'
                        ? onChange({ mode: 'day-of-month', day: 1 })
                        : onChange({ mode: 'nth-weekday', nth: 1, weekday: 'MO' })
                }
                options={modeOptions}
            />
            {mode === 'day-of-month' && (
                <TextField
                    label="Día del mes"
                    type="number"
                    min={1}
                    max={31}
                    step={1}
                    value={rrule?.mode === 'day-of-month' ? rrule.day : 1}
                    onChange={(e) =>
                        onChange({
                            mode: 'day-of-month',
                            day: Math.min(31, Math.max(1, Number(e.target.value) || 1)),
                        })
                    }
                />
            )}
            {mode === 'nth-weekday' && rrule?.mode === 'nth-weekday' && (
                <>
                    <SelectField
                        label="Nº"
                        value={String(rrule.nth)}
                        onChange={(e) =>
                            onChange({
                                mode: 'nth-weekday',
                                nth: Number(e.target.value) as -1 | 1 | 2 | 3 | 4,
                                weekday: rrule.weekday,
                            })
                        }
                        options={nthOptions}
                    />
                    <SelectField
                        label="Día"
                        value={rrule.weekday}
                        onChange={(e) =>
                            onChange({
                                mode: 'nth-weekday',
                                nth: rrule.nth,
                                weekday: e.target.value as Weekday,
                            })
                        }
                        options={weekdayOptions}
                    />
                </>
            )}
        </div>
    );
}
