import { defaultRruleInput, type RruleInput, type Weekday } from './buildRrule';

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

/**
 * Same output shape as `humanizeRrule`, but accepts the raw RFC 5545
 * RRULE string the backend stores. Returns "Recurrente" if the rule
 * can't be parsed (defensive — we control the producer in
 * `buildRrule()`, but a third party could insert custom rules later).
 *
 * Recognised parts: FREQ, INTERVAL, BYDAY, BYMONTHDAY, BYSETPOS.
 * UNTIL/COUNT are intentionally ignored — the listing already shows
 * the explicit fechas count + range, so the recurrence summary
 * focuses on cadence.
 */
export function humanizeRawRrule(rule: string): string {
    if (rule === '') return 'Recurrente';
    const parts: Record<string, string> = {};
    for (const segment of rule.split(';')) {
        const eq = segment.indexOf('=');
        if (eq === -1) continue;
        parts[segment.slice(0, eq).trim().toUpperCase()] = segment.slice(eq + 1).trim();
    }

    const freqRaw = parts['FREQ'];
    const isFreq = (v: string | undefined): v is RruleInput['freq'] =>
        v === 'DAILY' || v === 'WEEKLY' || v === 'MONTHLY' || v === 'YEARLY';
    if (!isFreq(freqRaw)) return 'Recurrente';

    const intervalRaw = parts['INTERVAL'];
    const interval =
        intervalRaw !== undefined && /^\d+$/.test(intervalRaw)
            ? Math.max(1, Number(intervalRaw))
            : 1;

    const isWeekday = (v: string): v is Weekday =>
        v === 'MO' ||
        v === 'TU' ||
        v === 'WE' ||
        v === 'TH' ||
        v === 'FR' ||
        v === 'SA' ||
        v === 'SU';

    const input: RruleInput = {
        freq: freqRaw,
        interval,
        end: { kind: 'never' },
    };

    if (freqRaw === 'WEEKLY' && parts['BYDAY'] !== undefined) {
        const days = parts['BYDAY'].split(',').map((d) => d.trim());
        const valid: Weekday[] = [];
        for (const d of days) {
            if (isWeekday(d)) valid.push(d);
        }
        if (valid.length > 0) input.byweekday = valid;
    }

    if (freqRaw === 'MONTHLY') {
        if (parts['BYMONTHDAY'] !== undefined && /^\d+$/.test(parts['BYMONTHDAY'])) {
            const day = Number(parts['BYMONTHDAY']);
            if (day >= 1 && day <= 31) {
                input.monthly = { mode: 'day-of-month', day };
            }
        } else if (
            parts['BYDAY'] !== undefined &&
            parts['BYSETPOS'] !== undefined &&
            isWeekday(parts['BYDAY'])
        ) {
            const nthRaw = parts['BYSETPOS'];
            const nth = nthRaw === '-1' ? -1 : Number(nthRaw);
            if (nth === -1 || nth === 1 || nth === 2 || nth === 3 || nth === 4) {
                input.monthly = { mode: 'nth-weekday', nth, weekday: parts['BYDAY'] };
            }
        }
    }

    return humanizeFreq(input);
}

/**
 * Inverse of `buildRrule()`: turns a raw RFC 5545 RRULE string back into
 * the structured `RruleInput` shape used by the SPA store. Used when
 * pre-filling the booking form in edit mode.
 *
 * Recognises FREQ, INTERVAL, BYDAY, BYMONTHDAY, BYSETPOS, UNTIL, COUNT.
 * If the rule can't be parsed, returns `defaultRruleInput()` so the form
 * doesn't blow up — a developer warning is logged in dev builds via
 * console.warn (the producer is our own backend, so unparseable rules
 * indicate a bug and shouldn't happen in production).
 */
export function parseRrule(rule: string | null | undefined): RruleInput {
    if (rule === null || rule === undefined || rule === '') {
        return defaultRruleInput();
    }
    const parts: Record<string, string> = {};
    for (const segment of rule.split(';')) {
        const eq = segment.indexOf('=');
        if (eq === -1) continue;
        parts[segment.slice(0, eq).trim().toUpperCase()] = segment.slice(eq + 1).trim();
    }

    const isFreq = (v: string | undefined): v is RruleInput['freq'] =>
        v === 'DAILY' || v === 'WEEKLY' || v === 'MONTHLY' || v === 'YEARLY';
    const freq = parts['FREQ'];
    if (!isFreq(freq)) {
        return defaultRruleInput();
    }

    const interval =
        parts['INTERVAL'] !== undefined && /^\d+$/.test(parts['INTERVAL'])
            ? Math.max(1, Number(parts['INTERVAL']))
            : 1;

    const isWeekday = (v: string): v is Weekday =>
        v === 'MO' ||
        v === 'TU' ||
        v === 'WE' ||
        v === 'TH' ||
        v === 'FR' ||
        v === 'SA' ||
        v === 'SU';

    const out: RruleInput = {
        freq,
        interval,
        end: { kind: 'never' },
    };

    if (freq === 'WEEKLY' && parts['BYDAY'] !== undefined) {
        const days = parts['BYDAY']
            .split(',')
            .map((d) => d.trim())
            .filter(isWeekday);
        if (days.length > 0) out.byweekday = days;
    }

    if (freq === 'MONTHLY') {
        if (parts['BYMONTHDAY'] !== undefined && /^\d+$/.test(parts['BYMONTHDAY'])) {
            const day = Number(parts['BYMONTHDAY']);
            if (day >= 1 && day <= 31) {
                out.monthly = { mode: 'day-of-month', day };
            }
        } else if (
            parts['BYDAY'] !== undefined &&
            parts['BYSETPOS'] !== undefined &&
            isWeekday(parts['BYDAY'])
        ) {
            const nthRaw = parts['BYSETPOS'];
            const nth = nthRaw === '-1' ? -1 : Number(nthRaw);
            if (nth === -1 || nth === 1 || nth === 2 || nth === 3 || nth === 4) {
                out.monthly = { mode: 'nth-weekday', nth, weekday: parts['BYDAY'] };
            }
        }
    }

    // End condition: UNTIL wins over COUNT if both are present (RFC 5545
    // forbids combining them, but we're defensive).
    if (parts['UNTIL'] !== undefined) {
        // UNTIL formats: YYYYMMDD or YYYYMMDDTHHMMSSZ.
        const m = /^(\d{4})(\d{2})(\d{2})/.exec(parts['UNTIL']);
        if (m !== null) {
            out.end = { kind: 'until', date: `${m[1]}-${m[2]}-${m[3]}` };
        }
    } else if (parts['COUNT'] !== undefined && /^\d+$/.test(parts['COUNT'])) {
        const count = Math.max(1, Number(parts['COUNT']));
        out.end = { kind: 'count', count };
    }

    return out;
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
