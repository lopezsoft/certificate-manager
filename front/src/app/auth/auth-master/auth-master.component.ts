import {Component, OnInit} from '@angular/core';
import {Subject} from "rxjs";
import {takeUntil} from "rxjs/operators";
import {FormGroup} from "@angular/forms";
import {TranslateService} from '@ngx-translate/core';

import {CoreConfigService} from "../../../@core/services/config.service";
import {Router} from "@angular/router";
import TokenService from "../../utils/token.service";

@Component({
  selector: 'app-auth-master',
  templateUrl: './auth-master.component.html',
})
export class AuthMasterComponent implements OnInit {
  public customForm  !: FormGroup;
  private _unsubscribeAll: Subject<any>;
  public coreConfig: any;
  loading     : boolean = false;
  constructor(
    public _coreConfigService: CoreConfigService,
    public translate: TranslateService,
    public router: Router,
    public authService: TokenService
  ) {
    this._unsubscribeAll = new Subject();
    
    // Configure the layout
    this._coreConfigService.config = {
      layout: {
        navbar: {
          hidden: true
        },
        menu: {
          hidden: true
        },
        footer: {
          hidden: true
        },
        customizer: false,
        enableLocalStorage: false
      }
    };
    
    translate.setDefaultLang('es');
    
    // the lang to use, if the lang isn't available, it will use the current loader to get them
    translate.use('es');
  }
  // convenience getter for easy access to form fields
  get f() {
    return this.customForm.controls;
  }
  ngOnInit(): void {
    // Subscribe to config changes
    this._coreConfigService.config.pipe(takeUntil(this._unsubscribeAll)).subscribe(config => {
      this.coreConfig = config;
    });
    if (this.authService.isAuthenticated()) {
      this.router.navigate([`/dashboard`]);
    }
  }
  
  isInvalid(controlName: string) : boolean {
    const ts  = this;
    const frm = ts.customForm;
    return frm.get(controlName)?.invalid && frm.get(controlName)?.touched || false;
  }
  
  onValidateForm(form: FormGroup): void {
    Object.values(form.controls).forEach(ele => {
      ele.markAllAsTouched();
    });
  }
  
  ngOnDestroy(): void {
    // Unsubscribe from all subscriptions
    this._unsubscribeAll.next(true);
    this._unsubscribeAll.complete();
  }
  
}
