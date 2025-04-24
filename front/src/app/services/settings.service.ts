import {Injectable} from '@angular/core';
import {Observable} from 'rxjs';
import {JsonResponse} from '../interfaces';
import {map} from 'rxjs/operators';
import {SettingEntry} from "../models/general-model";
import {HttpResponsesService} from "../utils";

@Injectable({
  providedIn: 'root'
})
export class SettingsService {
  public settings: SettingEntry[] = [];
  constructor(
    protected http: HttpResponsesService,
  ) { }
  public getSettings() {
    this.http.get('/company/settings').subscribe((response: any) => {
      this.settings = response.settings;
    });
  }
  public getSettingData(): Observable<SettingEntry> {
    return this.http.get('/company/settings')
      .pipe(
        map((response: any) => {
          this.settings = response.settings;
          return response.settings;
        })
      );
  }

  public saveSettings(settings: any = []): Observable<JsonResponse> {
    const data  = {
      records: JSON.stringify(settings),
    };
    return this.http.put('/company/settings', data);
  }

  /**
   * Is Use Sender mail active
   * @returns {boolean}
   */
    public isUseSenderMailActive(): boolean {
      const setting = this.settings.find(s => s.setting.key_value === 'USESENDERMAIL');
      return setting ? (parseInt(setting.value, 10) === 1) : false;
    }
}
