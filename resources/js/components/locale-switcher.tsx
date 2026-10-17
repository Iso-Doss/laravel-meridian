import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/lib/i18n';
import type { SharedProps } from '@/types';

export function LocaleSwitcher() {
    const { meridian, nav } = usePage<SharedProps>().props;
    const { t } = useTranslation();

    if (meridian.locales.length < 2) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" aria-label={t('Language')} title={t('Language')}>
                    <Languages />
                    <span className="uppercase">{meridian.locale}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>{t('Language')}</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={meridian.locale}
                    onValueChange={(locale) => router.post(nav.locale, { locale }, { preserveScroll: true })}
                >
                    {meridian.locales.map((locale) => (
                        <DropdownMenuRadioItem key={locale.code} value={locale.code}>
                            {locale.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
