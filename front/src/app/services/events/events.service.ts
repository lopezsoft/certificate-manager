import { Injectable } from '@angular/core';
import {DocumentReception} from '../../interfaces/events';
import {map} from 'rxjs/operators';
import {Observable} from 'rxjs';
import {Items} from '../../models/products-model';
import {JsonResponse} from '../../interfaces';
import {HttpResponsesService} from "../../utils";
import {LoadMaskService} from "../load-mask.service";

@Injectable({
  providedIn: 'root'
})
export class EventsService {
  public receptionDocuments: DocumentReception[] = [];
  public receptionDocument: DocumentReception;
  constructor(
    public http: HttpResponsesService,
    public mask: LoadMaskService,
  ) { }

  public sentEventMail(eventId: number): Observable<JsonResponse> {
    return this.http.post(`/events/send/mail/${eventId}`)
      .pipe(map((res) => {
        return res;
      }));
  }
  public sendEvent(params: any, trackId: string): Observable<JsonResponse> {
    return this.http.post(`/events/send/${trackId}`, params)
      .pipe(map((res) => {
        return res;
      }));
  }
  public getEventsById(id: number): Observable<DocumentReception> {
    return this.http.get(`/events/document-receptions/${id}`)
      .pipe(map((res) => {
        const data: any = res.dataRecords.data;
        this.receptionDocument = data[0];
        return data[0];
      }));
  }

  public getEventStatus(trackId: string): Observable<JsonResponse> {
    return this.http.get('/events/status/' + trackId)
      .pipe(map((res) => {
        return res;
      }));
  }
  public getEvents(query: any = {}): Observable<DocumentReception[]> {
    return this.http.get('/events/document-receptions', query)
      .pipe(map((res) => {
        this.receptionDocuments = res.dataRecords.data;
        return this.receptionDocuments;
      }));
  }

  eventsImportTrackId(trackId: any): Observable<Items[]> {
    const ts  = this;
    return ts.http.post(`/events/import-track-id`, {trackId})
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }

  eventsImportExcel(params: any): Observable<Items[]> {
    const ts  = this;
    return ts.http.post(`/events/import-excel`, params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }
}
