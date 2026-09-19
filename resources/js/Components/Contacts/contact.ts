export interface ContactRow {
    id: string | null;
    name: string;
    position: string | null;
    email: string | null;
    phone: string | null;
    is_primary: boolean;
}
