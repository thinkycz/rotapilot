export type UserRole = 'store_manager' | 'employee';

export interface AuthUser {
    id: number;
    email: string;
    locale: string;
    email_verified_at: string | null;
    role: UserRole;
    is_active: boolean;
}

export interface AppMeta {
    name: string;
    locale: string;
    locales: string[];
}

export interface FlashProps {
    success: string | null;
    error: string | null;
    shift_modal_success?: string | null;
    shift_modal_error?: string | null;
    create_shift_modal_success?: string | null;
    create_shift_modal_error?: string | null;
    availability_modal_success?: string | null;
    availability_modal_error?: string | null;
    employee_login_generated_password?: string | null;
}

export interface SharedProps {
    [key: string]: unknown;

    app: AppMeta;
    auth: {
        user: AuthUser | null;
    };
    flash: FlashProps;
    errors: Record<string, string>;
}
