
export interface SearchParams {
	search?: string;
	searchType?: number;
	start?: number;
	limit?: number;
	isDocumentSupport : boolean;
}

export interface  Body {
  GetNumberingRangeResponse : {
    GetNumberingRangeResult : {
      OperationCode: string;
      OperationDescription: string;
      ResponseList: string;
    }
  }
}

export interface ResponseDian {
  ResponseDian  : {
    Envelope: {
      Envelope: string;
      Body    : Body;
    }
  }
  success : boolean;
  message : string;
}

export interface Report {
  pathFile: string;
  success: boolean;
}

export interface DataRecords {
  current_page: number;
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: [];
  next_page_url: string;
  path: string;
  per_page: number;
  prev_page_url: string;
  to: number;
  total: number;
  data: [];
}
export interface JsonResponse {
  ResponseDian  : {
    Envelope: {
      Envelope: string;
      Body: Body;
    },
    StatusDescription: string;
    StatusMessage: string;
    IsValid: string;
    ErrorMessage: string;
    StatusCode: string;
    XmlBase64Bytes: string;
    XmlFileName: string;
    XmlDocumentKey: string;
  },
  success: boolean;
  message: string;
  isValid: boolean;
  StatusMessage: string;
  pathFile: string;
  StatusDescription: string;
  ErrorMessage: string;
  records: [];
  total: number;
  error: string;
  report: Report;
  dataRecords: DataRecords;
}


