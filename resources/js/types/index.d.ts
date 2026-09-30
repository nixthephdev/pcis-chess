export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        student: { name: string; username: string | null } | null;
    };
    flash: {
        status: string | null;
    };
};
