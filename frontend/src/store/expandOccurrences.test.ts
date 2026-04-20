import { describe, expect, it } from 'vitest';

import { expandOccurrences, toIsoDay } from './expandOccurrences';
import type { RruleInput } from './buildRrule';

const iso = (dates: Date[]): string[] => dates.map(toIsoDay);

describe('expandOccurrences', () => {
    it('DAILY with COUNT yields exactly N dates', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 1,
            end: { kind: 'count', count: 3 },
        };
        const out = expandOccurrences(r, new Date(2026, 3, 1));
        expect(iso(out)).toEqual(['2026-04-01', '2026-04-02', '2026-04-03']);
    });

    it('DAILY INTERVAL=2 skips days', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 2,
            end: { kind: 'count', count: 4 },
        };
        const out = expandOccurrences(r, new Date(2026, 3, 1));
        expect(iso(out)).toEqual(['2026-04-01', '2026-04-03', '2026-04-05', '2026-04-07']);
    });

    it('WEEKLY BYDAY=TU UNTIL stops on the right date', () => {
        const r: RruleInput = {
            freq: 'WEEKLY',
            interval: 1,
            byweekday: ['TU'],
            end: { kind: 'until', date: '2026-04-28' },
        };
        // Start on Tuesday 2026-04-07. Expected: 7, 14, 21, 28.
        const out = expandOccurrences(r, new Date(2026, 3, 7));
        expect(iso(out)).toEqual(['2026-04-07', '2026-04-14', '2026-04-21', '2026-04-28']);
    });

    it('WEEKLY BYDAY=MO,WE,FR produces each matching day in order', () => {
        const r: RruleInput = {
            freq: 'WEEKLY',
            interval: 1,
            byweekday: ['MO', 'WE', 'FR'],
            end: { kind: 'count', count: 6 },
        };
        // Start 2026-04-06 (Monday): Mon, Wed, Fri, Mon, Wed, Fri.
        const out = expandOccurrences(r, new Date(2026, 3, 6));
        expect(iso(out)).toEqual([
            '2026-04-06',
            '2026-04-08',
            '2026-04-10',
            '2026-04-13',
            '2026-04-15',
            '2026-04-17',
        ]);
    });

    it('MONTHLY BYMONTHDAY=15 yields same day each month', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'day-of-month', day: 15 },
            end: { kind: 'count', count: 3 },
        };
        const out = expandOccurrences(r, new Date(2026, 3, 15));
        expect(iso(out)).toEqual(['2026-04-15', '2026-05-15', '2026-06-15']);
    });

    it('MONTHLY BYDAY=MO BYSETPOS=3 gives third Monday', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'nth-weekday', nth: 3, weekday: 'MO' },
            end: { kind: 'count', count: 3 },
        };
        const out = expandOccurrences(r, new Date(2026, 3, 1));
        expect(iso(out)).toEqual(['2026-04-20', '2026-05-18', '2026-06-15']);
    });

    it('MONTHLY last Friday via nth=-1', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'nth-weekday', nth: -1, weekday: 'FR' },
            end: { kind: 'count', count: 3 },
        };
        const out = expandOccurrences(r, new Date(2026, 3, 1));
        // Last Fri of Apr=24, May=29, Jun=26.
        expect(iso(out)).toEqual(['2026-04-24', '2026-05-29', '2026-06-26']);
    });

    it('YEARLY yields same month/day across years', () => {
        const r: RruleInput = {
            freq: 'YEARLY',
            interval: 1,
            end: { kind: 'count', count: 3 },
        };
        const out = expandOccurrences(r, new Date(2026, 5, 15));
        expect(iso(out)).toEqual(['2026-06-15', '2027-06-15', '2028-06-15']);
    });

    it('never-ending rules are clamped by safetyDays', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 1,
            end: { kind: 'never' },
        };
        const out = expandOccurrences(r, new Date(2026, 3, 1), { safetyDays: 5 });
        expect(out.length).toBe(6); // day 0..5 inclusive
    });

    it('caps at maxCount to avoid runaway expansion', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 1,
            end: { kind: 'never' },
        };
        const out = expandOccurrences(r, new Date(2026, 0, 1), {
            safetyDays: 10000,
            maxCount: 7,
        });
        expect(out.length).toBe(7);
    });

    it('MONTHLY BYMONTHDAY=31 gracefully clamps to month length', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'day-of-month', day: 31 },
            end: { kind: 'count', count: 4 },
        };
        const out = expandOccurrences(r, new Date(2026, 0, 31));
        // Jan 31, Feb 28 (clamped), Mar 31, Apr 30 (clamped).
        expect(iso(out)).toEqual(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30']);
    });
});

describe('toIsoDay', () => {
    it('formats YYYY-MM-DD with padding', () => {
        expect(toIsoDay(new Date(2026, 0, 3))).toBe('2026-01-03');
    });
});
