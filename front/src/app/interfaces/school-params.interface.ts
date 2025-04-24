
export interface ISchoolParams {
    id: number;
    schoolId: number;
    uuid: string;
}

export interface ISchool {
    active: number;
    country_id: number;
    database_name: string;
    folder_name: string;
    id: number;
    lockdate: string;
    nameschool: string;
    state: number;
    statecode: string;
}


export interface IVotingData {
    availability_status: number;
    candidacy_id: number;
    candidacy_name: string;
    candidate_id: number;
    enrollment_id: number;
    grado: string;
    group_name: string;
    id_grade: number;
    image: string;
    names: string;
    number: string;
    type: number;
    selected: boolean;
}


export interface IControl {
    id: number;
    year: string;
    school_name: string;
    voting_table: number;
    discrimination_based: number;
    null_vote_attempts: number;
    voting_type: number;
    certificate_header: string;
    start_date: string;
    closing_time: string;
    start_time: string;
    state: number;
}

export interface IValidate {
    startDate: string;
    startDateCarbon: string;
    dateEnd: string;
    dateEndCarbon: string;
    currentDate: string;
    control: IControl;
}

export interface IExtraData {
    uuid: string;
    start: string;
    end: string | null;
    ip: string;
    path: string;
}

export interface IPollingStation {
    id: number;
    year: string;
    table_name: string;
    table_number: string;
    table_location: string;
    start_time: string;
    closing_time: string | null;
    extra_data: string;
    state: number;
}

export interface IStudent {
    id: number;
    id_grade: number;
    id_group: string;
    names: string;
    id_state: number;
    id_headquarters: number;
    id_student: number;
    id_study_day: number;
    year: number;
}
// Path: src/app/interfaces/user.interface.ts