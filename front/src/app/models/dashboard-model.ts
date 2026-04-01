import {DocumentStatusEnum} from "../common/enums/DocumentStatus";

export interface ConsumeByYear {
    company_id: number;
    company_name: string;
    total: number; // El backend retorna número, no string
    nyear: number;
    request_status: DocumentStatusEnum;
    life?: number; // Años de vigencia del certificado
}

export interface ConsumeByYearAndMonth extends ConsumeByYear {
    nmonth: number; // El backend retorna número
    monthname: string;
}
