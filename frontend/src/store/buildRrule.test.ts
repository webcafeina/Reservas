import { describe, expect, it } from 'vitest';

import { buildRrule, defaultRruleInput, toIcalDate, type RruleInput } from './buildRrule';

describe('buildRrule', () => {
    it('builds a plain daily rule without interval when interval=1', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 1,
            end: { kind: 'count', count: 5 },
        };
        expect(buildRrule(r)).toBe('FREQ=DAILY;COUNT=5');
    });

    it('emits INTERVAL only when > 1', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 3,
            end: { kind: 'count', count: 5 },
        };
        expect(buildRrule(r)).toBe('FREQ=DAILY;INTERVAL=3;COUNT=5');
    });

    it('builds weekly by weekday, sorted Mon→Sun', () => {
        const r: RruleInput = {
            freq: 'WEEKLY',
            interval: 1,
            byweekday: ['FR', 'MO', 'WE'],
            end: { kind: 'count', count: 10 },
        };
        expect(buildRrule(r)).toBe('FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=10');
    });

    it('omits BYDAY when byweekday is empty', () => {
        const r: RruleInput = {
            freq: 'WEEKLY',
            interval: 2,
            byweekday: [],
            end: { kind: 'count', count: 4 },
        };
        expect(buildRrule(r)).toBe('FREQ=WEEKLY;INTERVAL=2;COUNT=4');
    });

    it('builds monthly by day-of-month', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'day-of-month', day: 15 },
            end: { kind: 'count', count: 6 },
        };
        expect(buildRrule(r)).toBe('FREQ=MONTHLY;BYMONTHDAY=15;COUNT=6');
    });

    it('rejects day-of-month outside 1..31', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'day-of-month', day: 32 },
            end: { kind: 'count', count: 1 },
        };
        expect(() => buildRrule(r)).toThrow();
    });

    it('builds monthly Nth weekday (e.g., 3rd Monday)', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'nth-weekday', nth: 3, weekday: 'MO' },
            end: { kind: 'count', count: 6 },
        };
        expect(buildRrule(r)).toBe('FREQ=MONTHLY;BYDAY=MO;BYSETPOS=3;COUNT=6');
    });

    it('supports last-weekday-of-month via nth=-1', () => {
        const r: RruleInput = {
            freq: 'MONTHLY',
            interval: 1,
            monthly: { mode: 'nth-weekday', nth: -1, weekday: 'FR' },
            end: { kind: 'count', count: 3 },
        };
        expect(buildRrule(r)).toBe('FREQ=MONTHLY;BYDAY=FR;BYSETPOS=-1;COUNT=3');
    });

    it('builds yearly', () => {
        const r: RruleInput = {
            freq: 'YEARLY',
            interval: 1,
            end: { kind: 'until', date: '2030-06-15' },
        };
        expect(buildRrule(r)).toBe('FREQ=YEARLY;UNTIL=20300615T235959Z');
    });

    it('serialises UNTIL with trailing 235959Z to include the whole day', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 1,
            end: { kind: 'until', date: '2026-12-31' },
        };
        expect(buildRrule(r)).toContain('UNTIL=20261231T235959Z');
    });

    it('omits end clause when kind=never', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 1,
            end: { kind: 'never' },
        };
        expect(buildRrule(r)).toBe('FREQ=DAILY');
    });

    it('rejects interval <= 0', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 0,
            end: { kind: 'count', count: 3 },
        };
        expect(() => buildRrule(r)).toThrow();
    });

    it('rejects count <= 0', () => {
        const r: RruleInput = {
            freq: 'DAILY',
            interval: 1,
            end: { kind: 'count', count: 0 },
        };
        expect(() => buildRrule(r)).toThrow();
    });

    it('defaultRruleInput is a valid starting shape', () => {
        const d = defaultRruleInput();
        expect(d.freq).toBe('WEEKLY');
        expect(d.interval).toBe(1);
    });
});

describe('toIcalDate', () => {
    it('strips hyphens from an ISO date', () => {
        expect(toIcalDate('2026-04-20')).toBe('20260420');
    });

    it('rejects malformed dates', () => {
        expect(() => toIcalDate('2026/04/20')).toThrow();
        expect(() => toIcalDate('abcd-ef-gh')).toThrow();
    });
});
