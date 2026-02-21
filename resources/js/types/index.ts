export * from './navigation';
export * from './ui';

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    sidebarOpen: boolean;
    [key: string]: unknown;
};
