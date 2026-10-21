export type JobStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'silenced';

export interface Locale {
    code: string;
    label: string;
}

export interface SharedProps {
    meridian: {
        name: string;
        poll: number;
        locale: string;
        /** "rtl" for Arabic. */
        direction: 'ltr' | 'rtl';
        locales: Locale[];
        translations: Record<string, string>;
        /** False for read-only users: no retry, delete, cancel or monitoring action. */
        can_manage: boolean;
        /** Seconds a cancelled running job has to stop by itself. */
        cancel_timeout: number;
    };
    nav: {
        dashboard: string;
        jobs: Record<'all' | JobStatus, string>;
        monitoring: string;
        errors: string;
        schedule: string;
        audit: string;
        batches: string;
        metrics: string;
        locale: string;
    };
    flash: { success: string | null; error: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Job {
    uuid: string;
    name: string;
    short_name: string;
    connection: string;
    queue: string;
    status: JobStatus;
    delayed: boolean;
    attempts: number;
    retries: number;
    tags: string[];
    batch_id: string | null;
    wait: number | null;
    runtime: number | null;
    queued_at: number | null;
    completed_at: number | null;
    failed_at: number | null;
    /** Percent reported by a running job (TracksProgress), else null. */
    progress: number | null;
    progress_message: string | null;
    /** Estimated seconds before the job completes. */
    remaining: number | null;
    /** A running job asked to stop. */
    cancelling: boolean;
    url: string;
    retry_url: string | null;
}

export interface RetryAttempt {
    status: JobStatus;
    runtime: number | null;
    exception: string | null;
    retried_at: number;
    finished_at: number | null;
}

export interface JobDetails extends Job {
    job_id: string | null;
    worker: string | null;
    available_at: number | null;
    reserved_at: number | null;
    payload: string | null;
    data: { properties: { name: string; value: string }[]; error: string | null } | null;
    retry_history: RetryAttempt[];
    exception: string | null;
    batch_url: string | null;
    delete_url: string | null;
    /** The failed jobs with the same cause. */
    error_group: { count: number; url: string } | null;
    /** The form changing the data of the failed job before retrying it, when allowed. */
    edit: { url: string; fields: EditableField[] } | null;
}

export interface EditableField {
    name: string;
    type: 'string' | 'int' | 'float' | 'bool' | 'json' | 'object' | 'setting';
    value: string;
    editable: boolean;
}

export interface ErrorGroup {
    fingerprint: string;
    class: string;
    message: string;
    file: string | null;
    line: number | null;
    count: number;
    jobs: string[];
    queues: string[];
    first_seen: number;
    last_seen: number;
    url: string;
    sample_url: string;
    retry_url: string | null;
}

export interface AuditEntry {
    action: string;
    label: string;
    subject: string | null;
    details: Record<string, string | number | boolean>;
    user: string | null;
    ip: string | null;
    created_at: number;
}

export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: { prev: string | null; next: string | null };
}

export interface Workload {
    connection: string;
    queue: string;
    pending: number;
    delayed: number;
    reserved: number;
    wait: number;
    processes: number;
}

export interface Worker {
    name: string;
    hostname: string;
    pid: number;
    supervisor: string | null;
    connection: string;
    queue: string;
    status: 'idle' | 'working';
    jobs_processed: number;
    memory: number;
    started_at: number | null;
    last_seen_at: number | null;
}

export interface Supervisor {
    name: string;
    short_name: string;
    hostname: string;
    pid: number;
    status: 'running' | 'paused';
    connection: string | null;
    balance: string | null;
    processes: Record<string, number>;
    total_processes: number;
}

export interface Batch {
    id: string;
    name: string;
    total: number;
    pending: number;
    failed: number;
    processed: number;
    progress: number;
    status: 'running' | 'finished' | 'failed' | 'cancelled';
    created_at: number | null;
    finished_at: number | null;
    cancelled_at: number | null;
    url: string;
    retry_url: string | null;
}

export interface ScheduleRun {
    status: 'running' | 'success' | 'failed' | 'skipped';
    exit_code: number | null;
    runtime: number | null;
    started_at: number;
    finished_at: number | null;
    exception?: string | null;
    host?: string | null;
}

export interface ScheduledTask {
    id: number;
    name: string;
    expression: string;
    timezone: string | null;
    repeat_seconds: number | null;
    background: boolean;
    without_overlapping: boolean;
    on_one_server: boolean;
    next_run_at: number | null;
    last_run: ScheduleRun | null;
    recent: ScheduleRun[];
    missed_at: number | null;
    health: 'running' | 'success' | 'failed' | 'skipped' | 'missed' | 'never';
    url?: string;
}
