import { Component, OnInit } from '@angular/core';

import { takeUntil } from 'rxjs/operators';
import { Subject } from 'rxjs';

import { CoreConfigService } from '@core/services/config.service';
import {Router} from "@angular/router";
import {MessagesService} from "../../utils";
import {environment} from "../../../environments/environment";

@Component({
  selector: 'app-not-authorized',
  templateUrl: './not-authorized.component.html',
  styleUrls: ['./not-authorized.component.scss']
})
export class NotAuthorizedComponent implements OnInit {
  public coreConfig: any;

  // Private
  private _unsubscribeAll: Subject<any>;
  
  constructor(
    private _coreConfigService: CoreConfigService,
    private router: Router,
    private msg: MessagesService,
  ) {
    this._unsubscribeAll = new Subject();

    // Configure the layout
    this._coreConfigService.config = {
      layout: {
        navbar: {
          hidden: true
        },
        footer: {
          hidden: true
        },
        menu: {
          hidden: true
        },
        customizer: false,
        enableLocalStorage: false
      }
    };
  }

  // Lifecycle Hooks
  // -----------------------------------------------------------------------------------------------------

  /**
   * On init
   */
  ngOnInit(): void {
    // Subscribe to config changes
    this._coreConfigService.config.pipe(takeUntil(this._unsubscribeAll)).subscribe(config => {
      this.coreConfig = config;
    });
  }

  /**
   * On destroy
   */
  ngOnDestroy(): void {
    // Unsubscribe from all subscriptions
    this._unsubscribeAll.next(true);
    this._unsubscribeAll.complete();
  }
  
  resetAccess() {
    this.msg.confirm('Cerrar sesión', '¿Está seguro de que desea cerrar sesión?')
      .then((result) => {
        if (result.isConfirmed) {
          localStorage.setItem('hasUpdate', 'true');
          localStorage.removeItem('currentUser');
          localStorage.removeItem(environment.APIJWT);
          setTimeout(() => {
            this.router.navigate(['/auth/login']);
          }, 100);
        }
      });
  }
}
