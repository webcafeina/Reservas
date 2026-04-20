/**
 * Client-side expander that mirrors what the backend's RecurrenceExpander
 * does. Used by the calendar UI so we can show upcoming occurrences without
 * a network round-trip on every RRULE change.
 *
 * The source of truth is still the backend (re-validates on submit). This
 * expander covers the same shapes that {@link RruleInput} can express; the
 * two must stay in sync. A shared unit-test fixture list in `buildRrule.test`
 * would be ideal — for now the individual rule shapes are tested below.
 */

import { WEEKDAY_ORDER, type RruleInput, type Weekday } from './buildRrule';

const MAX_OCCURRENCES = 500;

const WEEKDAY_TO_JS_DAY: Record<Weekday, number> = {
    SU: 0,
    MO: 1,
    TU: 2,
    WE: 3,
    TH: 4,
    FR: 5,
    SA: 6,
};

export interface ExpandOptions {
    safetyDays?: number;
    maxCount?: number;
}

export function expandOccurrences(
    input: RruleInput,
    start: Date,
    options: ExpandOptions = {},
): Date[] {
    const safetyDays = options.safetyDays ?? 365;
    const maxCount = options.maxCount ?? MAX_OCCURRENCES;

    const startDay = toMidnight(start);

    const boundary: Date =
        input.end.kind === 'until'
            ? parseIsoDate(input.end.date) ?? addDays(startDay, safetyDays)
            : addDays(startDay, safetyDays);

    const countLimit = input.end.kind === 'count' ? Math.max(1, Math.floor(input.end.count)) : Infinity;

    const out: Date[] = [];
    const push = (d: Date): boolean => {
        if (d > boundary) return false; // past the safety window → stop
        if (d < startDay) return true; // before DTSTART → skip but continue
        out.push(new Date(d));
        return out.length < countLimit && out.length < maxCount;
    };

    switch (input.freq) {
        case 'DAILY':
            expandDaily(startDay, input.interval, push);
            break;
        case 'WEEKLY':
            expandWeekly(startDay, input.interval, input.byweekday ?? [], push);
            break;
        case 'MONTHLY':
            expandMonthly(startDay, input, push);
            break;
        case 'YEARLY':
            expandYearly(startDay, input.interval, push);
            break;
    }

    return out;
}

function expandDaily(start: Date, interval: number, push: (d: Date) => boolean): void {
    let d = new Date(start);
    // eslint-disable-next-line no-constant-condition
    while (true) {
        if (!push(d)) return;
        d = addDays(d, Math.max(1, interval));
    }
}

function expandWeekly(
    start: Date,
    interval: number,
    byweekday: Weekday[],
    push: (d: Date) => boolean,
): void {
    const targetDays =
        byweekday.length > 0
            ? [...byweekday].sort(
                  (a, b) => WEEKDAY_ORDER.indexOf(a) - WEEKDAY_ORDER.indexOf(b),
              )
            : [jsDayToWeekday(start.getDay())];

    // Anchor the "week" at the Monday of the start's week so BYDAY iteration
    // is predictable regardless of start day.
    let weekStart = startOfWeek(start);

    // eslint-disable-next-line no-constant-condition
    while (true) {
        for (const wd of targetDays) {
            const offset = (WEEKDAY_TO_JS_DAY[wd] + 6) % 7; // Mon=0 … Sun=6
            const occurrence = addDays(weekStart, offset);
            if (!push(occurrence)) return;
        }
        weekStart = addDays(weekStart, 7 * Math.max(1, interval));
    }
}

function expandMonthly(start: Date, input: RruleInput, push: (d: Date) => boolean): void {
    const mode = input.monthly?.mode ?? 'day-of-month';
    let cursor = new Date(start.getFullYear(), start.getMonth(), 1);

    // eslint-disable-next-line no-constant-condition
    while (true) {
        let target: Date | null = null;

        if (mode === 'day-of-month') {
            const day =
                input.monthly?.mode === 'day-of-month' ? input.monthly.day : start.getDate();
            const lastDay = daysInMonth(cursor.getFullYear(), cursor.getMonth());
            const actualDay = Math.min(day, lastDay);
            target = new Date(cursor.getFullYear(), cursor.getMonth(), actualDay);
        } else if (mode === 'nth-weekday' && input.monthly?.mode === 'nth-weekday') {
            target = nthWeekdayOfMonth(
                cursor.getFullYear(),
                cursor.getMonth(),
                input.monthly.weekday,
                input.monthly.nth,
            );
        }

        if (target !== null) {
            if (!push(target)) return;
        }
        cursor = addMonths(cursor, Math.max(1, input.interval));
    }
}

function expandYearly(start: Date, interval: number, push: (d: Date) => boolean): void {
    let y = start.getFullYear();
    const month = start.getMonth();
    const day = start.getDate();
    // eslint-disable-next-line no-constant-condition
    while (true) {
        const d = new Date(y, month, day);
        if (!push(d)) return;
        y += Math.max(1, interval);
    }
}

// ---------- helpers ----------

function toMidnight(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function addDays(d: Date, n: number): Date {
    const r = new Date(d);
    r.setDate(r.getDate() + n);
    return r;
}

function addMonths(d: Date, n: number): Date {
    const r = new Date(d);
    r.setMonth(r.getMonth() + n);
    return r;
}

function daysInMonth(year: number, month: number): number {
    return new Date(year, month + 1, 0).getDate();
}

function startOfWeek(d: Date): Date {
    // Monday as start of week.
    const day = d.getDay(); // 0=Sun
    const diff = (day + 6) % 7; // days since Monday
    return toMidnight(addDays(d, -diff));
}

function jsDayToWeekday(jsDay: number): Weekday {
    const map: Weekday[] = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
    const wd = map[jsDay];
    if (wd === undefined) {
        throw new Error(`Invalid JS day: ${jsDay}`);
    }
    return wd;
}

function nthWeekdayOfMonth(
    year: number,
    month: number,
    weekday: Weekday,
    nth: -1 | 1 | 2 | 3 | 4,
): Date {
    const targetDay = WEEKDAY_TO_JS_DAY[weekday];
    if (nth === -1) {
        const last = daysInMonth(year, month);
        for (let d = last; d >= 1; d--) {
            const date = new Date(year, month, d);
            if (date.getDay() === targetDay) return date;
        }
    } else {
        let found = 0;
        for (let d = 1; d <= daysInMonth(year, month); d++) {
            const date = new Date(year, month, d);
            if (date.getDay() === targetDay) {
                found++;
                if (found === nth) return date;
            }
        }
    }
    // Fallback (rare: e.g. "4th Friday" when there are only 4 Fridays).
    return new Date(year, month, 1);
}

function parseIsoDate(s: string): Date | null {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    if (!m) return null;
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
}

export function toIsoDay(d: Date): string {
    const y = d.getFullYear().toString().padStart(4, '0');
    const m = (d.getMonth() + 1).toString().padStart(2, '0');
    const day = d.getDate().toString().padStart(2, '0');
    return `${y}-${m}-${day}`;
}
