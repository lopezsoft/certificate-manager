export interface ErrorResponse {
  error: {
    message: string;
  };
  message: string;
  success: boolean;
  ok: boolean;
  status: string;
  statusText: string;
  url: string;
}
