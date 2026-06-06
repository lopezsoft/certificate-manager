export interface ErrorResponse {
  error: {
    message: string;
    errors?: Record<string, string[]>;
  };
  message: string;
  success: boolean;
  ok: boolean;
  status: number;
  statusText: string;
  url: string;
}
