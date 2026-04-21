import type { RruleInput, Weekday } from './buildRrule';

const DATE_FORMATTER_ES = new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const WEEKDAY_NAMES: Record<Weekday, string> = {
    MO: 'lunes',
    TU: 'martes',
    WE: 'miércoles',
    TH: 'jueves',
    FR: 'viernes',
    SA: 'sábado',
    SU: 'domingo',
};

const ORDINAL_NAMES: Record<-1 | 1 | 2 | 3 | 4, string> = {
    [1]: 'primer',
    [2]: 'segundo',
    [3]: 'tercer',
    [4]: 'cuarto',
    [-1]: 'último',
};

function joinWeekdaysEs(days: Weekday[]): string {
    const names = days.map((d) => WEEKDAY_NAMES[d]);
    if (names.length <= 1) return names.join('');
    const head = names.slice(0, -1).join(', ');
    return `${head} y ${names[names.length - 1]}`;
}

function humanizeFreq(input: RruleInput): string {
    const n = input.interval;
    switch (input.freq) {
        case 'DAILY':
            return n === 1 ? 'Diaria' : `Cada ${n} días`;
        case 'WEEKLY': {
            const base = n === 1 ? 'Semanal' : `Cada ${n} semanas`;
            if (input.byweekday && input.byweekday.length > 0) {
                return `${base}, los ${joinWeekdaysEs(input.byweekday)}`;
            }
            return base;
        }
        case 'MONTHLY': {
            const base = n === 1 ? 'Mensual' : `Cada ${n} meses`;
            if (input.monthly !== undefined) {
                if (input.monthly.mode === 'day-of-month') {
                    return `${base}, el día ${input.monthly.day}`;
                }
                const ord = ORDINAL_NAMES[input.monthly.nth];
                return `${base}, el ${ord} ${WEEKDAY_NAMES[input.monthly.weekday]}`;
            }
            return base;
        }
        case 'YEARLY':
            return n === 1 ? 'Anual' : `Cada ${n} años`;
    }
}

/** Human-readable summary of the recurrence rule (no UNTIL/COUNT part). */
export function humanizeRrule(input: RruleInput): string {
    return humanizeFreq(input);
}

/** Human-readable summary of the end condition. */
export function humanizeRruleEnd(end: RruleInput['end']): string {
    switch (end.kind) {
        case 'until':
            if (end.date === '') return 'Sin fecha de fin definida';
            return `Hasta el ${DATE_FORMATTER_ES.format(new Date(`${end.date}T00:00:00Z`))}`;
        case 'count':
            return end.count === 1 ? 'Una única ocurrencia' : `Durante ${end.count} ocurrencias`;
        case 'never':
            return 'Sin fin (limitado a 1 año)';
    }
}
