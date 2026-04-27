/**
 * Date formatting utilities. Internal storage stays ISO (YYYY-MM-DD) —
 * these helpers only transform for display.
 */

/**
 * Convert an ISO date "YYYY-MM-DD" to Spanish "DD-MM-YYYY".
 * Empty/null/malformed inputs return '—' so it's safe to drop into JSX.
 */
export function formatDateEs(iso: string | null | undefined): string {
    if (iso === null || iso === undefined || iso === '') return '—';
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
    if (m === null) return iso;
    return `${m[3]}-${m[2]}-${m[1]}`;
}

/**
 * Convert an ISO datetime "YYYY-MM-DD HH:MM:SS" (or with T separator) to
 * Spanish "DD-MM-YYYY HH:MM". Falls back to the date-only formatter if no
 * time component is present.
 */
export function formatDateTimeEs(iso: string | null | undefined): string {
    if (iso === null || iso === undefined || iso === '') return '—';
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(iso);
    if (m === null) return formatDateEs(iso);
    return `${m[3]}-${m[2]}-${m[1]} ${m[4]}:${m[5]}`;
}
