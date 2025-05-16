import {DocumentStatusEnum} from "../common/enums/DocumentStatus";

export interface ConsumeByYear {
    company_id: number;
    company_name: string;
    total: string;
    nyear: number;
    request_status: DocumentStatusEnum;
}

export interface ConsumeByYearAndMonth extends ConsumeByYear {
    nmonth: string;
    monthname: string;
}
