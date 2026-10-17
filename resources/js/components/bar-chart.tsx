import { cn } from '@/lib/utils';

export interface Bar {
    key: string | number;
    value: number;
    label: string;
}

/** Minimal accessible bar chart (SVG, theme colours, a tooltip per bar). */
export function BarChart({ bars, ariaLabel, className }: { bars: Bar[]; ariaLabel: string; className?: string }) {
    const max = Math.max(1, ...bars.map((bar) => bar.value));
    const width = 100 / Math.max(1, bars.length);

    return (
        <svg viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label={ariaLabel} className={cn('h-32 w-full', className)}>
            {bars.map((bar, index) => {
                const height = bar.value ? Math.max(1, (bar.value / max) * 39) : 0.6;

                return (
                    <rect
                        key={bar.key}
                        x={index * width + width * 0.12}
                        y={40 - height}
                        width={width * 0.76}
                        height={height}
                        rx={0.4}
                        className={cn('fill-primary transition-opacity hover:opacity-100', bar.value ? 'opacity-80' : 'opacity-15')}
                    >
                        <title>{bar.label}</title>
                    </rect>
                );
            })}
        </svg>
    );
}
