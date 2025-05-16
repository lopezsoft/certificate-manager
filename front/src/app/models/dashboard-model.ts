export interface ConsumeByYear {
    company_id: number;
    company_name: string;
    total: string;
    nyear: number;
}

export interface ConsumeByYearAndMonth extends ConsumeByYear {
    nmonth: string;
    monthname: string;
}
