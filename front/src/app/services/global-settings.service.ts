import { Injectable } from '@angular/core';
import { BlockUI, NgBlockUI } from 'ng-block-ui';
@Injectable({
  providedIn: 'root'
})
export class GlobalSettingsService {
  @BlockUI() blockUI: NgBlockUI;
  public isSoftwareEnabled  = false;
  public loading = false;
  public blockUIMessage = 'Procesando petición...';
  constructor() { }
  public showBlockUI(customMessage?: string ) {
    this.blockUI.start(customMessage || this.blockUIMessage);
  }
  public hideBlockUI() {
    this.blockUI.stop();
  }
}
