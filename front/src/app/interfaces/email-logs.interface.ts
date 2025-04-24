
export interface EmailLogsInterface {
		id: number;
		company_id: number;
		company_name: string;
		customer_id: number;
		person_name: string;
		document_id: number;
		document_number: string;
		type_document_id: number;
		type_document_name: string;
		message_id: string;
		email: string;
		status: string;
		status_translated: string;
		opens: number;
		last_opened_at: string;
		last_opened_at_original: string;
		clicks: number;
		last_clicked_at: string;
		last_clicked_at_original: string;
		delivered_at: string;
		delivered_at_original: string;
		bounced_at: string;
		bounced_at_original: string;
		complained_at: string;
		complained_at_original: string;
		bounce_type: string;
		bounce_subtype: string;
		created_at: string;
		created_at_original: string;
		updated_at: string;
		updated_at_original: string;
}
