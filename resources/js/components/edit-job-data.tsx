import { useForm } from '@inertiajs/react';
import { Loader2, RotateCcw } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input, NativeSelect, Textarea } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import type { EditableField } from '@/types';

/** Change the data of a failed job, then push it back onto its queue. */
export function EditJobData({ url, fields, onDone }: { url: string; fields: EditableField[]; onDone: () => void }) {
    const { t } = useTranslation();
    const editable = fields.filter((field) => field.editable);
    const form = useForm<{ data: Record<string, string> }>({
        data: Object.fromEntries(editable.map((field) => [field.name, field.value])),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onDone });
    };

    const set = (name: string, value: string) => form.setData('data', { ...form.data.data, [name]: value });

    return (
        <form onSubmit={submit}>
            <p className="border-b bg-warning/10 px-5 py-3 text-sm text-warning">
                {t('Change the values that made the job fail, then retry it. Models, enums, dates and other objects are kept as they are.')}
            </p>
            <div className="divide-y">
                {fields.map((field) => (
                    <div key={field.name} className="grid gap-1 px-5 py-3 sm:grid-cols-[11rem_1fr]">
                        <label htmlFor={`field-${field.name}`} className="text-sm text-muted-foreground">
                            {field.name}
                            <span className="ms-1.5 font-mono text-[11px] opacity-70">{field.type}</span>
                        </label>
                        <div className="min-w-0">
                            {!field.editable ? (
                                <span className="font-mono text-xs break-all text-muted-foreground">{field.value}</span>
                            ) : field.type === 'bool' ? (
                                <NativeSelect id={`field-${field.name}`} value={form.data.data[field.name]} onChange={(event) => set(field.name, event.target.value)}>
                                    <option value="true">true</option>
                                    <option value="false">false</option>
                                </NativeSelect>
                            ) : field.type === 'json' || (field.type === 'string' && (field.value.length > 80 || field.value.includes('\n'))) ? (
                                <Textarea
                                    id={`field-${field.name}`}
                                    value={form.data.data[field.name]}
                                    onChange={(event) => set(field.name, event.target.value)}
                                    rows={Math.min(12, Math.max(2, field.value.split('\n').length))}
                                    className="font-mono text-xs"
                                    aria-invalid={!!errors[`data.${field.name}`]}
                                />
                            ) : (
                                <Input
                                    id={`field-${field.name}`}
                                    value={form.data.data[field.name]}
                                    onChange={(event) => set(field.name, event.target.value)}
                                    inputMode={field.type === 'int' || field.type === 'float' ? 'decimal' : undefined}
                                    className="font-mono text-xs"
                                    aria-invalid={!!errors[`data.${field.name}`]}
                                />
                            )}
                            {errors[`data.${field.name}`] && <p className="mt-1 text-xs text-destructive">{errors[`data.${field.name}`]}</p>}
                        </div>
                    </div>
                ))}
            </div>
            {errors.data && <p className="px-5 pb-3 text-sm text-destructive">{errors.data}</p>}
            <div className="flex justify-end gap-2 border-t px-5 py-3">
                <Button type="button" variant="outline" onClick={onDone} disabled={form.processing}>
                    {t('Cancel')}
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Loader2 className="animate-spin" /> : <RotateCcw />}
                    {t('Retry with these values')}
                </Button>
            </div>
        </form>
    );
}
