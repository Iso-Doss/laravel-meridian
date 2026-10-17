export type JobStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface Locale {
    code: string;
    label: string;
}

export interface SharedProps {
    meridian: {
        name: string;
        poll: number;
        locale: string;
        locales: Locale[];
        translations: Record<string, string>;
    };
    nav: {
        dashboard: string;
        jobs: Record<'all' | JobStatus, string>;
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
    url: string;
}

export interface JobDetails extends Job {
    job_id: string | null;
    worker: string | null;
    available_at: number | null;
    reserved_at: number | null;
    payload: string | null;
    exception: string | null;
    batch_url: string | null;
    retry_url: string | null;
    delete_url: string | null;
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
