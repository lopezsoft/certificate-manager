import {Company} from "./shipping-intetface";
import {Cities, IdentityDocuments, TypeOrganzation} from "../models/general-model";
import {DocumentStatusEnum, FileDocumentTypeEnum, UserOfChangeEnum} from "../common/enums/DocumentStatus";
import {Users} from "../models/users-model";


export interface FileManager {
    id: number;
    certificate_request_id: number;
    created_at: string;
    extension_file: string;
    file_name: string;
    file_path: string;
    last_modified: string;
    created_at_formatted: string;
    updated_at_formatted: string;
    last_modified_formatted: string;
    location: string;
    mime_type: string;
    file_size: string;
    type_file: string;
    updated_at: string;
    status: string;
    uuid: string;
    document_type: FileDocumentTypeEnum;
}

export interface CertificateRequest {
    id: number;
    uuid: string;
    company_id: number | string;
    city_id: number | string;
    identity_document_id: number | string;
    type_organization_id: number | string;
    company_name: string;
    dni: string;
    dv: number;
    address: string;
    postal_code: string;
    mobile?: string;
    phone?: string;
    image: string;
    legal_representative: string;
    info: string;
    life: number;
    request_status: string;
    document_number: string;
    city: Cities;
    identity: IdentityDocuments;
    organization: TypeOrganzation;
    files: FileManager[];
    company: Company;
    history: TimelineEvent[];
    checked?: boolean;
    pin: string;
    expiration_date: string;
    created_at: string;
    updated_at: string;
    updated_at_formatted: string;
    created_at_formatted: string;
    expiration_date_formatted: string;
}

export interface TimelineEvent {
    id: number | string;
    user_of_change: UserOfChangeEnum;
    status: DocumentStatusEnum;
    comments: string;
    created_at: Date | string;
    user: Users;
    created_at_formatted: string;
    updated_at_formatted: string;
}

export interface DocumentViewerState {
    isVisible: boolean;
    sourceUrl: string | null;
    title: string | null;
}
