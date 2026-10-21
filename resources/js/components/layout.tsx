import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BellOff,
    Bookmark,
    BarChart3,
    Bug,
    CalendarClock,
    CheckCircle2,
    Clock,
    Globe,
    Layers,
    LayoutDashboard,
    ListChecks,
    Loader,
    Menu,
    RefreshCw,
    ScrollText,
    Eye,
    XCircle,
    X,
    type LucideIcon,
} from 'lucide-react';
import { Dialog } from 'radix-ui';
import { type ReactNode, useEffect, useState } from 'react';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { useAutoRefreshSetting } from '@/hooks/use-auto-refresh';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    exact?: boolean;
}

function pathOf(url: string): string {
    return new URL(url, window.location.origin).pathname.replace(/\/+$/, '') || '/';
}

function useNavigation(): { title: string; items: NavItem[] }[] {
    const { nav } = usePage<SharedProps>().props;

    return [
        { title: '', items: [{ label: 'Dashboard', href: nav.dashboard, icon: LayoutDashboard, exact: true }] },
        {
            title: 'Jobs',
            items: [
                { label: 'Pending', href: nav.jobs.pending, icon: Clock },
                { label: 'Processing', href: nav.jobs.processing, icon: Loader },
                { label: 'Completed', href: nav.jobs.completed, icon: CheckCircle2 },
                { label: 'Failed', href: nav.jobs.failed, icon: XCircle },
                { label: 'Silenced', href: nav.jobs.silenced, icon: BellOff },
                { label: 'All jobs', href: nav.jobs.all, icon: ListChecks, exact: true },
            ],
        },
        {
            title: 'Insights',
            items: [
                { label: 'Errors', href: nav.errors, icon: Bug },
                { label: 'Schedule', href: nav.schedule, icon: CalendarClock },
                { label: 'Monitoring', href: nav.monitoring, icon: Bookmark },
                { label: 'Batches', href: nav.batches, icon: Layers },
                { label: 'Metrics', href: nav.metrics, icon: BarChart3 },
                { label: 'Audit log', href: nav.audit, icon: ScrollText },
            ],
        },
    ];
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const { url, props } = usePage<SharedProps>();
    const { t } = useTranslation();
    const current = pathOf(url);

    const isActive = (item: NavItem) => {
        const target = pathOf(item.href);

        return item.exact ? current === target : current === target || current.startsWith(target + '/');
    };

    return (
        <div className="flex h-full flex-col gap-6 p-4">
            <Link href={props.nav.dashboard} className="flex items-center gap-2.5 px-2 text-base font-semibold" onClick={onNavigate}>
                <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <Globe className="size-4.5" />
                </span>
                <span className="leading-tight">
                    Meridian
                    <span className="block text-xs font-normal text-muted-foreground">{props.meridian.name}</span>
                </span>
            </Link>

            <nav className="flex flex-col gap-5">
                {useNavigation().map((section) => (
                    <div key={section.title} className="flex flex-col gap-0.5">
                        {section.title && (
                            <div className="px-2 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">{t(section.title)}</div>
                        )}
                        {section.items.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={onNavigate}
                                className={cn(
                                    'flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent hover:text-accent-foreground',
                                    isActive(item) && 'bg-accent font-medium text-accent-foreground',
                                )}
                            >
                                <item.icon className="size-4" />
                                {t(item.label)}
                            </Link>
                        ))}
                    </div>
                ))}
            </nav>
        </div>
    );
}

function AutoRefreshToggle() {
    const [enabled, setEnabled] = useAutoRefreshSetting();
    const { t } = useTranslation();
    const { poll } = usePage<SharedProps>().props.meridian;

    if (poll <= 0) {
        return null;
    }

    return (
        <Button
            variant="ghost"
            size="sm"
            aria-pressed={enabled}
            onClick={() => setEnabled(!enabled)}
            title={enabled ? t('Auto-refresh every :seconds s', { seconds: poll }) : t('Auto-refresh paused')}
        >
            <RefreshCw className={cn(enabled && 'animate-[spin_3s_linear_infinite] text-primary')} />
            <span className="hidden sm:inline">{enabled ? t('Live') : t('Paused')}</span>
        </Button>
    );
}

function Flash() {
    const { flash } = usePage<SharedProps>().props;
    const [visible, setVisible] = useState(true);

    useEffect(() => setVisible(true), [flash.success, flash.error]);

    const message = flash.error ?? flash.success;

    if (!message || !visible) {
        return null;
    }

    return (
        <div
            role="status"
            className={cn(
                'mb-6 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm',
                flash.error ? 'border-destructive/30 bg-destructive/10 text-destructive' : 'border-success/30 bg-success/10 text-success',
            )}
        >
            {message}
            <button type="button" onClick={() => setVisible(false)} className="opacity-70 hover:opacity-100">
                <X className="size-4" />
            </button>
        </div>
    );
}

export function Layout({
    title,
    badge,
    actions,
    children,
}: {
    title: string;
    badge?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const { meridian } = usePage<SharedProps>().props;
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <div className="min-h-screen lg:grid lg:grid-cols-[15rem_1fr]">
            <Head title={title} />

            <aside className="sticky top-0 hidden h-screen border-r bg-sidebar lg:block">
                <Sidebar />
            </aside>

            <div className="min-w-0">
                <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b bg-background/80 px-4 backdrop-blur sm:px-6">
                    <Dialog.Root open={menuOpen} onOpenChange={setMenuOpen}>
                        <Dialog.Trigger asChild>
                            <Button variant="ghost" size="icon" className="lg:hidden" aria-label={t('Menu')}>
                                <Menu />
                            </Button>
                        </Dialog.Trigger>
                        <Dialog.Portal>
                            <Dialog.Overlay className="fixed inset-0 z-40 bg-black/50" />
                            <Dialog.Content className="fixed inset-y-0 left-0 z-50 w-64 border-r bg-sidebar shadow-lg">
                                <Dialog.Title className="sr-only">{t('Menu')}</Dialog.Title>
                                <Sidebar onNavigate={() => setMenuOpen(false)} />
                            </Dialog.Content>
                        </Dialog.Portal>
                    </Dialog.Root>

                    <Activity className="hidden size-4 text-muted-foreground sm:block" />
                    <span className="truncate text-sm text-muted-foreground">{t('Database queues')}</span>

                    <div className="ml-auto flex items-center gap-1">
                        {!meridian.can_manage && (
                            <span
                                className="mr-1 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs text-muted-foreground"
                                title={t('You can browse Meridian but not act on the jobs.')}
                            >
                                <Eye className="size-3.5" />
                                <span className="hidden sm:inline">{t('Read only')}</span>
                            </span>
                        )}
                        <AutoRefreshToggle />
                        <LocaleSwitcher />
                        <ThemeToggle />
                    </div>
                </header>

                <main className="mx-auto w-full max-w-7xl p-4 sm:p-6">
                    <Flash />
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <h1 className="flex min-w-0 flex-wrap items-center gap-3 text-xl font-semibold tracking-tight">
                            <span className="min-w-0 break-all">{title}</span>
                            {badge}
                        </h1>
                        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                    </div>
                    {children}
                </main>
            </div>
        </div>
    );
}
