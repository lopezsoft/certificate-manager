import { NgForm, Validators, FormBuilder } from '@angular/forms';
import { Router, ActivatedRoute } from "@angular/router";
import {Component, OnInit, ElementRef, ViewEncapsulation} from '@angular/core';

// Services
import { HttpResponsesService, MessagesService } from '../../utils';
import { TranslateService } from '@ngx-translate/core';

// Interfaces
import { ViewChild } from '@angular/core';
import TokenService from '../../utils/token.service';
import { ErrorResponse } from '../../interfaces';
import {AuthMasterComponent} from "../auth-master/auth-master.component";
import {CoreConfigService} from "../../../@core/services/config.service";
import {GlobalSettingsService} from "../../services/global-settings.service";

@Component({
  selector: 'app-recover',
  templateUrl: './recover.component.html',
  styleUrls: ['./recover.component.scss'],
	encapsulation: ViewEncapsulation.None
})
export class RecoverComponent extends AuthMasterComponent implements OnInit {

  @ViewChild('focusElement') focusElement!: ElementRef;
  @ViewChild('f') forogtPasswordForm!: NgForm;


  constructor(
            public fb: FormBuilder,
            public api: HttpResponsesService,
            public msg: MessagesService,
            public router: Router,
            public aRouter: ActivatedRoute,
            public _coreConfigService: CoreConfigService,
            public translate: TranslateService,
            public _token: TokenService,
            public _globalSettings: GlobalSettingsService,
    ) {
    super(_coreConfigService, translate, router, _token);

    this.customForm = this.fb.group({
      email: ['', Validators.required]
    });
  }

  /**
* On init
*/
  ngOnInit(): void {
    super.ngOnInit();
  }

  // On submit click, reset form fields
  onSubmit() {
    const ts  = this;
    const lang= ts.translate;
    if(ts.customForm.invalid) {
      ts.msg.toastMessage(lang.instant('titleMessages.emptyFields'), lang.instant('bodyMessages.emptyFields'), 4);
      return;
    }

    ts._globalSettings.loading = true;
    ts.api.post('/forgot-password', { email: ts.customForm.get('email')?.value }).
      subscribe({
				next: (resp) => {
					ts._globalSettings.loading = false;
					if (!resp.success) {
						ts.msg.errorMessage('', resp.message);
						return;
					}
					ts.msg.onMessage('', resp.message);
				},
				error: (err: ErrorResponse) => {
					ts._globalSettings.loading = false;
					ts.msg.errorMessage('Error', err.error.message || err.message);
				}
			});
  }

  get invalidEmail(): boolean {
    return this.isInvalid('email');
  }

  get placeholderEmail(): string {
    return 'Correo electrónico';
  }
}
