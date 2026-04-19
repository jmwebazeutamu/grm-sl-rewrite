import type { Config as ZiggyConfig } from 'ziggy-js';

export interface AuthUser {
    id: number;
    username: string;
    email: string;
    name: string;
}

export interface FlashMessages {
    success?: string;
    error?: string;
    info?: string;
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: AuthUser | null;
            permissions: string[];
        };
        flash: FlashMessages;
        ziggy: ZiggyConfig & { location: string };
        app: {
            name: string;
            env: string;
        };
    }
}

export {};
