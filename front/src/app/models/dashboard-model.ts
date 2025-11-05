import {DocumentStatusEnum} from "../common/enums/DocumentStatus";

export interface ConsumeByYear {
    company_id: number;
    company_name: string;
    total: string;
    nyear: number;
    request_status: DocumentStatusEnum;
    life?: number; // Años de vigencia del certificado
}

export interface ConsumeByYearAndMonth extends ConsumeByYear {
    nmonth: string;
    monthname: string;
}
