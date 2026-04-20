import { useMemo, useState } from 'react';

import { toIsoDay } from '../store/expandOccurrences';

import styles from './OccurrenceCalendar.module.css';

interface OccurrenceCalendarProps {
    occurrences: Date[];
    excluded: string[];
    onToggleExclude: (iso: string) => void;
}

const MONTH_NAMES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

const WEEKDAY_HEADERS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

export function OccurrenceCalendar({
    occurrences,
    excluded,
    onToggleExclude,
}: OccurrenceCalendarProps): JSX.Element {
    const occurrenceSet = useMemo(() => new Set(occurrences.map(toIsoDay)), [occurrences]);
    const excludedSet = useMemo(() => new Set(excluded), [excluded]);

    const firstOccurrence = occurrences[0] ?? new Date();
    const [cursor, setCursor] = useState(() => startOfMonth(firstOccurrence));

    const monthsToShow = [cursor, addMonths(cursor, 1)];

    const occurrenceCount = occurrenceSet.size;
    const excludedCount = excludedSet.size;
    const effectiveCount = occurrences.filter((d) => !excludedSet.has(toIsoDay(d))).length;

    return (
        <div className={styles.wrapper}>
            <div className={styles.header}>
                <button
                    type="button"
                    onClick={() => setCursor(addMonths(cursor, -1))}
                    aria-label="Mes anterior"
                    className={styles.navBtn}
                >
                    ‹
                </button>
                <div className={styles.stats}>
                    <strong>{effectiveCount}</strong> ocurrencias
                    {excludedCount > 0 && (
                        <>
                            {' '}· <span className={styles.muted}>
                                {excludedCount} excluida{excludedCount === 1 ? '' : 's'}
                            </span>
                        </>
                    )}
                    {occurrenceCount === 0 && (
                        <span className={styles.muted}>Sin ocurrencias con los parámetros actuales.</span>
                    )}
                </div>
                <button
                    type="button"
                    onClick={() => setCursor(addMonths(cursor, 1))}
                    aria-label="Mes siguiente"
                    className={styles.navBtn}
                >
                    ›
                </button>
            </div>

            <div className={styles.months}>
                {monthsToShow.map((m) => (
                    <MonthGrid
                        key={`${m.getFullYear()}-${m.getMonth()}`}
                        month={m}
                        occurrenceSet={occurrenceSet}
                        excludedSet={excludedSet}
                        onClickDay={onToggleExclude}
                    />
                ))}
            </div>

            <ul className={styles.legend} aria-hidden="true">
                <li>
                    <span className={`${styles.swatch} ${styles.occurrence}`} />
                    Ocurrencia
                </li>
                <li>
                    <span className={`${styles.swatch} ${styles.excluded}`} />
                    Excluida
                </li>
            </ul>
        </div>
    );
}

interface MonthGridProps {
    month: Date;
    occurrenceSet: Set<string>;
    excludedSet: Set<string>;
    onClickDay: (iso: string) => void;
}

function MonthGrid({ month, occurrenceSet, excludedSet, onClickDay }: MonthGridProps): JSX.Element {
    const year = month.getFullYear();
    const monthIndex = month.getMonth();
    const firstOfMonth = new Date(year, monthIndex, 1);
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    // Monday-based: JS getDay() 0=Sun, we want 0=Mon.
    const firstWeekday = (firstOfMonth.getDay() + 6) % 7;

    const cells: Array<{ day: number; iso: string } | null> = [];
    for (let i = 0; i < firstWeekday; i++) {
        cells.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, monthIndex, d);
        cells.push({ day: d, iso: toIsoDay(date) });
    }
    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    return (
        <div className={styles.month}>
            <h4 className={styles.monthTitle}>
                {MONTH_NAMES[monthIndex]} {year}
            </h4>
            <div className={styles.grid} role="grid" aria-label={`${MONTH_NAMES[monthIndex] ?? ''} ${year}`}>
                {WEEKDAY_HEADERS.map((w, i) => (
                    <div key={`h-${i}`} className={styles.weekdayHeader} role="columnheader">
                        {w}
                    </div>
                ))}
                {cells.map((cell, idx) => {
                    if (cell === null) {
                        return <div key={`e-${idx}`} className={styles.emptyCell} />;
                    }
                    const isOccurrence = occurrenceSet.has(cell.iso);
                    const isExcluded = excludedSet.has(cell.iso);
                    const classes = [styles.cell];
                    if (isOccurrence) classes.push(styles.occurrence);
                    if (isExcluded) classes.push(styles.excluded);
                    const label = isOccurrence
                        ? isExcluded
                            ? `${cell.iso} (excluida, clic para restaurar)`
                            : `${cell.iso} (ocurrencia, clic para excluir)`
                        : cell.iso;
                    return (
                        <button
                            type="button"
                            key={cell.iso}
                            className={classes.join(' ')}
                            onClick={() => {
                                if (isOccurrence) onClickDay(cell.iso);
                            }}
                            disabled={!isOccurrence}
                            aria-label={label}
                        >
                            {cell.day}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function startOfMonth(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), 1);
}

function addMonths(d: Date, n: number): Date {
    return new Date(d.getFullYear(), d.getMonth() + n, 1);
}
