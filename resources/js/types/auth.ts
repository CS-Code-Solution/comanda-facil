export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: string;
    role_label: string;
    avatar?: string;
};

export type Auth = {
    user: User;
};
