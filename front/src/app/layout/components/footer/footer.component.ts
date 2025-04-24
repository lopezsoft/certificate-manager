import { OnInit, OnDestroy, Component } from '@angular/core';

import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import { CoreConfigService } from '@core/services/config.service';
import { environment } from 'environments/environment';

@Component({
  selector: 'footer',
  templateUrl: './footer.component.html'
})
export class FooterComponent implements OnInit, OnDestroy {
  public coreConfig	: any;
  public year : number = new Date().getFullYear();
	public versionApp : string = '1';

  // Private
  private _unsubscribeAll: Subject<any>;

  /**
   * Constructor
   *
   * @param {CoreConfigService} _coreConfigService
   */
  constructor(public _coreConfigService: CoreConfigService) {
    // Set the private defaults
    this._unsubscribeAll = new Subject();
  }

  // Lifecycle hooks
  // -----------------------------------------------------------------------------------------------------

  /**
   * On init
   */
  ngOnInit(): void {
    const ts    = this;
    // Subscribe to config changes
    ts._coreConfigService.config.pipe(takeUntil(ts._unsubscribeAll)).subscribe(config => {
      ts.coreConfig = config;
    });
    ts.versionApp	= localStorage.getItem('version_app') || environment.VERSION;
  }

  /**
   * On destroy
   */
  ngOnDestroy(): void {
    // Unsubscribe from all subscriptions
    this._unsubscribeAll.next({});
    this._unsubscribeAll.complete();
  }
}
