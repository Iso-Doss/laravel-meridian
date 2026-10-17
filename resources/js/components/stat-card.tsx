import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';

export function StatCard({ icon: Icon, label, value, hint }: { icon: LucideIcon; label: string; value: ReactNode; hint?: ReactNode }) {
    return (
        <Card className="p-4">
            <div className="flex items-center justify-between gap-2 text-xs font-medium text-muted-foreground">
                {label}
                <span className="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Icon className="size-4" />
                </span>
            </div>
            <div className="mt-2 text-2xl font-semibold tabular-nums">{value}</div>
            {hint && <div className="mt-0.5 truncate text-xs text-muted-foreground">{hint}</div>}
        </Card>
    );
}
