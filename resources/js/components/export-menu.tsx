import { Download, FileJson, FileSpreadsheet } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/lib/i18n';

/** Download the current list (with its filters) as CSV or JSON. */
export function ExportMenu({ url, query }: { url: string; query: Record<string, string> }) {
    const { t } = useTranslation();
    const link = (format: string) => `${url}?${new URLSearchParams({ ...query, format }).toString()}`;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download /> {t('Export')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <a href={link('csv')} download>
                        <FileSpreadsheet /> CSV
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={link('json')} download>
                        <FileJson /> JSON
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
