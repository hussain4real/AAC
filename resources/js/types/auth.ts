export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/** The current user's MAACC platform-administration access (Phase 8B). */
export type PlatformAccess = {
    roles: string[];
    permissions: string[];
    isSuperAdmin: boolean;
    isAdministrator: boolean;
};

/** Server-authoritative tenant/project access used by console data and nav. */
export type MaaccAccess = {
    roles: string[];
    permissions: string[];
    navigation: string[];
    projectIds: string[];
    isPlatformAdmin: boolean;
    roleLabel: string;
};

export type Auth = {
    user: User;
    platform: PlatformAccess;
    maacc: MaaccAccess;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
