import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { HttpResponsesService } from 'app/utils';

export interface AppNotification {
  id: string;
  data: {
    message?: string;
    title?: string;
    icon?: string;
    url?: string;
    [key: string]: any;
  };
  read_at: string | null;
  created_at: string;
  [key: string]: any;
}

@Injectable({
  providedIn: 'root'
})
export class NotificationsService {
  public notifications: AppNotification[] = [];
  public onApiDataChange: BehaviorSubject<AppNotification[]> = new BehaviorSubject([]);

  constructor(private _http: HttpResponsesService) {
    // Only load if user is authenticated (token exists)
    // Real fetching should probably be triggered by the component to avoid auth timing issues, 
    // but we leave this here for backwards compatibility or component can call it.
  }

  getNotifications(): Observable<AppNotification[]> {
    return this._http.get('/notifications').pipe(
      map((resp: any) => {
        this.notifications = resp.dataRecords?.data || resp.dataRecords || resp || [];
        this.onApiDataChange.next(this.notifications);
        return this.notifications;
      })
    );
  }

  markAsRead(id: string): Observable<any> {
    return this._http.post(`/notifications/${id}/read`, {}).pipe(
      map(resp => {
        const notif = this.notifications.find(n => n.id === id);
        if (notif) notif.read_at = new Date().toISOString();
        this.onApiDataChange.next(this.notifications);
        return resp;
      })
    );
  }

  markAllAsRead(): Observable<any> {
    return this._http.post('/notifications/read-all', {}).pipe(
      map(resp => {
        this.notifications.forEach(n => n.read_at = new Date().toISOString());
        this.onApiDataChange.next(this.notifications);
        return resp;
      })
    );
  }
}
