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

export interface AgentConversation {
    id: string;
    title: string;
    updated_at: string;
}

export interface AgentProposalAction {
    type: string;
    label: string;
    payload: Record<string, unknown>;
}

export interface AgentProposal {
    id: number;
    conversation_id: string;
    message_id: string | null;
    status: 'pending' | 'applied' | 'rejected' | 'failed';
    summary: string;
    actions: AgentProposalAction[];
    result: Record<string, unknown> | null;
    created_at: string | null;
}

export interface SharedProps {
    [key: string]: unknown;

    app: AppMeta;
    auth: {
        user: AuthUser | null;
    };
    flash: FlashProps;
    conversations: AgentConversation[];
    errors: Record<string, string>;
}
