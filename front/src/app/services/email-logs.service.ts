import { Injectable } from '@angular/core';
import {EmailLogsInterface} from '../interfaces/email-logs.interface';
import {map} from 'rxjs/operators';
import {Observable} from 'rxjs';
import {HttpResponsesService} from "../utils";

@Injectable({
  providedIn: 'root'
})
export class EmailLogsService {

  public emailLogs: EmailLogsInterface[] = [];

  constructor(
    private http: HttpResponsesService
  ) { }

    /**
     * Get all email logs
     * Obtiene todos los registros de email
     */
    public getAll(): Observable<EmailLogsInterface[]> {
      return this.http.get('/email-logs', {})
        .pipe(
          map((response) => {
            this.emailLogs = response.dataRecords.data;
            return this.emailLogs;
          }));
    }

    /**
     * Get one email log by id
     * Obtiene un registro de email por id
     */
    public getOne(id: number): Observable<EmailLogsInterface> {
      return this.http.get(`/email-logs/${id}`, {})
        .pipe(
          map((response) => {
            const emailLog = response.dataRecords.data as any;
            return emailLog[0];
          }));
    }

    /**
     * Find by document id
     * Búsqueda por id del documento
     */
    public findByDocumentId(documentId: number, params: any = {}): Observable<EmailLogsInterface[]> {
      return this.http.get(`/email-logs/document/${documentId}`, params)
        .pipe(
          map((response) => {
            this.emailLogs = response.dataRecords.data;
            return response.dataRecords.data;
          }));
    }
  }
