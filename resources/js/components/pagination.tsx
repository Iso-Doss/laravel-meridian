import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import type { Paginated } from '@/types';

export function Pagination({ paginator }: { paginator: Paginated<unknown> }) {
    const { t, locale } = useTranslation();
    const { meta, links } = paginator;

    if (meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm text-muted-foreground">
            <span>
                {t(':from–:to of :total', {
                    from: formatNumber(meta.from ?? 0, locale),
                    to: formatNumber(meta.to ?? 0, locale),
                    total: formatNumber(meta.total, locale),
                })}
            </span>
            <div className="flex gap-2">
                <Button variant="outline" size="sm" disabled={!links.prev} asChild={!!links.prev}>
                    {links.prev ? (
                        <Link href={links.prev} preserveScroll>
                            <ChevronLeft className="rtl:-scale-x-100" /> {t('Previous')}
                        </Link>
                    ) : (
                        <>
                            <ChevronLeft className="rtl:-scale-x-100" /> {t('Previous')}
                        </>
                    )}
                </Button>
                <Button variant="outline" size="sm" disabled={!links.next} asChild={!!links.next}>
                    {links.next ? (
                        <Link href={links.next} preserveScroll>
                            {t('Next')} <ChevronRight className="rtl:-scale-x-100" />
                        </Link>
                    ) : (
                        <>
                            {t('Next')} <ChevronRight className="rtl:-scale-x-100" />
                        </>
                    )}
                </Button>
            </div>
        </div>
    );
}
