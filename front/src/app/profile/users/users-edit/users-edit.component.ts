import { AfterViewInit, Component, ElementRef, OnInit, ViewChild } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { TranslateService } from '@ngx-translate/core';
import { HttpResponsesService, MessagesService } from '../../../utils';

import { UserTypes } from '../../../models/users-model';
import { FormComponent } from '../../../@core/components/forms';
import { UsersService } from '../../../services/users/users.service';
import TokenService from "../../../utils/token.service";

@Component({
  selector: 'app-users-edit',
  templateUrl: './users-edit.component.html',
  styleUrls: ['./users-edit.component.scss'],
})
export class UsersEditComponent extends FormComponent implements OnInit, AfterViewInit {
    // @ts-ignore
  @ViewChild('uploadFile') uploadFile: ElementRef | undefined;
    @ViewChild('imgUp') imgUp: ElementRef | undefined;
    // @ts-ignore
  @ViewChild('focusElement') focusElement: ElementRef | undefined;

    userTypes: UserTypes[] = [];
    title = 'Editar usuario';
    constructor(
      public fb: FormBuilder,
      public api: HttpResponsesService,
      public msg: MessagesService,
      public router: Router,
      public _token: TokenService,
      public translate: TranslateService,
      public aRouter: ActivatedRoute,
      public usersSer: UsersService,
    ) {
      super(fb, msg, api, _token, router, translate, aRouter);
      this.customForm = this.fb.group({
        type_id        : [1, [Validators.required]],
        first_name     : ['',[Validators.required, Validators.minLength(3)]],
        last_name      : ['',[Validators.required, Validators.minLength(3)]],
        active         : [true],
        email          : ['', [Validators.pattern('^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$'), Validators.required]]
      });
    }

    // VALIDATION
		get rf() {
			return this.customForm.controls;
		}

    get invalidFirstName(): boolean{
      return this.isInvalid('first_name');
    }

    get invalidLastName(): boolean{
      return this.isInvalid('last_name');
    }

		get invalidEmail() {
			return this.isInvalid('email');
		}


		// placeholder

		get placeholderEmail(): string {
			return this.translate.instant('placeholder.email');
		}


    ngOnInit(): void {
      super.ngOnInit();
      const ts    = this;
      ts.PutURL   = '/profile/';
      ts.PostURL  = '/profile';

      ts.showSpinner();

      ts.usersSer.getUserTypes()
        .subscribe((resp) => {
          ts.userTypes  = resp;
        });
    }

    ngAfterViewInit(): void {
      super.ngAfterViewInit();
      this.hideSpinner();
    }

    loadData(id: any = 0): void {
      super.loadData(id);
      const ts    = this;
      const frm   = ts.customForm;
      ts.editing  = true;
      ts.usersSer.getData({ uid: id})
			.subscribe({
				next: (resp) => {
					ts.hideSpinner();
					frm.setValue({
						type_id     : resp[0].type_id,
						first_name  : resp[0].first_name,
						last_name   : resp[0].last_name,
						active      : resp[0].active,
						email       : resp[0].email,
					});
					ts.imgData              = resp[0].avatarUrl ? resp[0].avatarUrl : '';
				},
				error: ()=> {
					ts.hideSpinner();
				}
			});
    }

    onResetForm() {
      const ts  = this;
      let frm   = ts.customForm;
      super.onResetForm(frm);
      frm.setValue({
        type_id     : 1,
        first_name  : '',
        last_name   : '',
        active      : true,
        email       : '',
      });
      ts.imgData = null;
    }
}
