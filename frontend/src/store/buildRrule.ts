/**
 * Builds RFC 5545 RRULE strings from a structured user input.
 *
 * All of the legacy recurrence shapes are covered:
 *   - daily / weekly / monthly / yearly
 *   - custom INTERVAL (every N days/weeks/…)
 *   - weekly BYDAY (specific weekdays)
 *   - monthly by day-of-month (BYMONTHDAY) or by "Nth weekday" (BYDAY+BYSETPOS)
 *   - end condition: UNTIL (date) or COUNT (N occurrences)
 *
 * The shape below is what the SPA store uses internally. The output string
 * is sent to the backend verbatim; the backend then re-expands via the
 * `simshaun/recurr` library.
 */

export type Freq = 'DAILY' | 'WEEKLY' | 'MONTHLY' | 'YEARLY';

export type Weekday = 'MO' | 'TU' | 'WE' | 'TH' | 'FR' | 'SA' | 'SU';

export const WEEKDAY_ORDER: readonly Weekday[] = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'] as const;

export type MonthlyMode =
    | { mode: 'day-of-month'; day: number }
    | { mode: 'nth-weekday'; nth: -1 | 1 | 2 | 3 | 4; weekday: Weekday };

export interface RruleInput {
    freq: Freq;
    interval: number; // >= 1
    /** Weekly only. Empty means "same day-of-week as DTSTART". */
    byweekday?: Weekday[];
    /** Monthly only. */
    monthly?: MonthlyMode;
    /** Ending condition. Exactly one of `until` or `count` is active. */
    end:
        | { kind: 'until'; date: string /* YYYY-MM-DD */ }
        | { kind: 'count'; count: number /* >= 1 */ }
        | { kind: 'never' };
}

/**
 * Converts an {@link RruleInput} into an RRULE string (no DTSTART line,
 * just the rule itself). Caller ensures `dtstart` is carried separately.
 */
export function buildRrule(input: RruleInput): string {
    if (input.interval < 1 || !Number.isFinite(input.interval)) {
        throw new Error('interval must be a positive integer');
    }

    const parts: string[] = [`FREQ=${input.freq}`];

    if (input.interval > 1) {
        parts.push(`INTERVAL=${Math.floor(input.interval)}`);
    }

    if (input.freq === 'WEEKLY' && input.byweekday && input.byweekday.length > 0) {
        const sorted = [...input.byweekday].sort(
            (a, b) => WEEKDAY_ORDER.indexOf(a) - WEEKDAY_ORDER.indexOf(b),
        );
        parts.push(`BYDAY=${sorted.join(',')}`);
    }

    if (input.freq === 'MONTHLY' && input.monthly) {
        if (input.monthly.mode === 'day-of-month') {
            const day = input.monthly.day;
            if (day < 1 || day > 31) {
                throw new Error('day must be between 1 and 31');
            }
            parts.push(`BYMONTHDAY=${Math.floor(day)}`);
        } else {
            parts.push(`BYDAY=${input.monthly.weekday}`);
            parts.push(`BYSETPOS=${input.monthly.nth}`);
        }
    }

    if (input.end.kind === 'until') {
        parts.push(`UNTIL=${toIcalDate(input.end.date)}T235959Z`);
    } else if (input.end.kind === 'count') {
        if (input.end.count < 1) {
            throw new Error('count must be at least 1');
        }
        parts.push(`COUNT=${Math.floor(input.end.count)}`);
    }

    return parts.join(';');
}

/**
 * `2026-06-30` → `20260630`. Used in UNTIL values.
 */
export function toIcalDate(iso: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!match) {
        throw new Error(`Invalid ISO date: "${iso}"`);
    }
    return `${match[1]}${match[2]}${match[3]}`;
}

export function defaultRruleInput(): RruleInput {
    return {
        freq: 'WEEKLY',
        interval: 1,
        byweekday: [],
        end: { kind: 'until', date: '' },
    };
}
