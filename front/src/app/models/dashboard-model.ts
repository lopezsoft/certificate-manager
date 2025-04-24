export interface ConsumeForCompany {
    nyear: number;
    parent_company: string;
    parent_dni: string;
    payment: number;
    total: string;
}

export interface ConsumeForCompanyByMonth extends ConsumeForCompany {
    nmonth: string;
}

export interface ConsumeForCustomer extends ConsumeForCompany {
    company_dni: string;
    company_name: string;
}

export interface ConsumeForCustomerByMonth extends ConsumeForCustomer {
    nmonth: string;
}
