import {AccountingDocuments} from '../models/accounting-model';
import {PaymentMethods, Resolutions} from '../models/general-model';


export interface People {
	id: number
	company_name: string;
	dni: string;
	email: string;
}

export interface DocumentReception {
	id: number;
	company_id: number;
	people_id: number;
	document_type_id: number;
	payment_method_id: number;
	cufe_cude: string;
	folio: string;
	prefix: string;
	issue_date: string;
	total: string;
	document_origin: string;
	metadata: null | any;
	document_type: AccountingDocuments;
	payment_method: PaymentMethods;
	events: DocumentEventInterface[];
	company_name: string;
	dni: string;
	people: People;
}


export interface EventData {
	ErrorMessage: {
		string: string;
	};
	IsValid: string;
	StatusCode: string;
	StatusDescription: string;
	StatusMessage: string;
	XmlBase64Bytes: string;
	XmlBytes: {
		_attributes: {
			nil: string;
		};
	};
	XmlDocumentKey: string;
	XmlFileName: string;
}

export interface TypeEvent {
	id: number;
	code: string;
	name: string;
	responsible: string;
}

export interface DocumentEventInterface {
	id: number;
	company_id: number;
	resolution_id: number;
	document_reception_id: number;
	type_event_id: number;
	event_number: number;
	date_event: string;
	description: string;
	xml_path: string;
	zip_path: string;
	event_data: EventData;
	document_status: string;
	type_event: TypeEvent;
	statusDescription: string;
	resolution: Resolutions;
	send_mail: number;
}

export interface DocumentReceptionPerson {
	id: number;
	identity_document_id: number;
	dni: string;
	dv: string;
	email: string;
	email_reception: string;
	first_name: string;
	last_name: string;
	job_title: string;
	department: string;
	send_events: boolean;
}