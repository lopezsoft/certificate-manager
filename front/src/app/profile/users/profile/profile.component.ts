import { AfterViewInit, Component, OnInit } from '@angular/core';
import { FormBuilder } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { TranslateService } from '@ngx-translate/core';
import { HttpResponsesService, MessagesService } from '../../../utils';

import { UserTypes } from '../../../models/users-model';
import { UsersService } from '../../../services/users/users.service';
import { UsersEditComponent } from '../users-edit/users-edit.component';
import TokenService from "../../../utils/token.service";

@Component({
  selector: 'app-profile',
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.scss']
})
export class ProfileComponent extends UsersEditComponent implements OnInit, AfterViewInit {
  userTypes: UserTypes[] = [];
  title = 'Perfil de usuario';
  constructor(
    public fb: FormBuilder,
    public api: HttpResponsesService,
    public _token: TokenService,
    public msg: MessagesService,
    public router: Router,
    public translate: TranslateService,
    public aRouter: ActivatedRoute,
    public usersSer: UsersService,
  ) {
    super(fb, api, msg, router, _token, translate, aRouter, usersSer);
  }

  ngOnInit(): void {
    super.ngOnInit();
		this.loadData();
  }

	loadData(id: any = 0): void {
    const ts    = this;
    const frm   = ts.customForm;
    ts.getCurrentUser();
    ts.usersSer.getProfile()
		.subscribe({
			next:(resp) => {
				localStorage.setItem('oldRoute', '/profile');
                const data = resp[0];
				ts.editing  = true;
				ts.hideSpinner();
				ts.uid  = data.id;
				frm.setValue({
					type_id     : data.type_id,
					first_name  : data.first_name,
					last_name   : data.last_name,
					active      : data.active,
					email       : data.email,
				});
				ts.imgData     = data.avatarUrl;
			},
			error: ()=> {
				ts.hideSpinner();
			}
		});
  }

  onAfterSave(resp: any){
    super.onAfterSave(resp);
    const ts      = this;
    this.editing  = true;
    ts.upCurrentUser({
        avatar    : resp.user.avatarUrl,
        id        : resp.user.id,
        email     : resp.user.email,
        lastName  : resp.user.last_name,
        firstName : resp.user.first_name,
    });
    setTimeout(() => {
        window.location.reload();
    }, 2000);
  }
}
