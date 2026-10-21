import { useTranslation } from '@/lib/i18n';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface HourlyPoint {
    time: number;
    processed: number;
    failed: number;
}

const LEVELS = ['bg-muted', 'bg-primary/20', 'bg-primary/40', 'bg-primary/65', 'bg-primary/90'];

/** Jobs per hour of the last days, one row per day, in the visitor's timezone. */
export function HourlyHeatmap({ points }: { points: HourlyPoint[] }) {
    const { t, locale } = useTranslation();
    const days = new Map<string, { label: string; hours: (HourlyPoint | null)[] }>();

    for (const point of points) {
        const date = new Date(point.time * 1000);
        const key = date.toDateString();

        if (!days.has(key)) {
            days.set(key, {
                label: new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric' }).format(date),
                hours: Array(24).fill(null),
            });
        }

        days.get(key)!.hours[date.getHours()] = point;
    }

    const rows = [...days.values()].slice(-7);
    // The scale follows the 95th percentile: one exceptional hour does not flatten the others.
    const counts = points.map((point) => point.processed + point.failed).filter((count) => count > 0).sort((a, b) => a - b);
    const max = Math.max(1, counts[Math.floor(counts.length * 0.95)] ?? 1);
    const level = (count: number) => (count === 0 ? 0 : Math.min(4, Math.ceil((count / max) * 4)));
    const failing = (point: HourlyPoint) => point.failed > 0 && point.failed / (point.processed + point.failed) >= 0.1;
    const hourLabel = (hour: number) => new Intl.DateTimeFormat(locale, { hour: 'numeric' }).format(new Date(2026, 0, 1, hour));

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[42rem] table-fixed border-separate border-spacing-1 text-xs">
                <thead>
                    <tr>
                        <th className="w-20" />
                        {Array.from({ length: 24 }, (_, hour) => (
                            <th key={hour} className="text-start font-normal whitespace-nowrap text-muted-foreground">
                                {hour % 3 === 0 ? hourLabel(hour) : ''}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.label}>
                            <th className="pe-2 text-start font-normal whitespace-nowrap text-muted-foreground">{row.label}</th>
                            {row.hours.map((point, hour) => {
                                const count = point ? point.processed + point.failed : 0;

                                return (
                                    <td
                                        key={hour}
                                        className={cn('h-5 rounded-sm', point ? LEVELS[level(count)] : 'bg-transparent', point && failing(point) && 'ring-1 ring-destructive/70 ring-inset')}
                                        title={
                                            point
                                                ? `${row.label} ${hourLabel(hour)} — ${t(':count job(s)', { count: formatNumber(count, locale) })}${point.failed ? `, ${t(':count failed', { count: formatNumber(point.failed, locale) })}` : ''}`
                                                : undefined
                                        }
                                    />
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
