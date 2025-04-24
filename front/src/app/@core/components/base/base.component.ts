import { Injectable, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { User } from '../../../auth/models';
import TokenService from '../../../utils/token.service';

@Injectable()
export class BaseComponent implements OnInit {
	public loading    : boolean = false;
	public activeLang = 'es';
	public maskSpinner: string = 'Realizando petición...';
	theme 						= 'bootstrap';
	public coreConfig : any;
  public currentUser!: User;


	constructor(
    	public _token: TokenService,
		public router: Router,
		public translate: TranslateService,
	) {
		const ts = this;
		ts.translate.setDefaultLang(ts.activeLang);
		ts.translate.use(ts.activeLang);
	}
	ngOnInit(): void {
    const ts    = this;
        // Subscribe to config changes
		ts.changeLanguage(ts.activeLang);
	}

	public changeLanguage(lang: string): void {
		this.activeLang = lang;
		this.translate.use(lang);
	}
	
	goToParent(): void {
		const currentUrl = this.router.url;
		const parentUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/'));
		this.router.navigate([parentUrl]);
	}


	/**
	 * Redirigir a la ruta indicada.
	 * @name : Nombre de la ruta
	 */
	goRoute(name: string): void {
		if (this._token.isAuthenticated()) {
			this.router.navigate([`/${name}`]);
		}
	}

	activeLoading(): void {
		this.loading = true;
	}

	disabledLoading(): void {
		this.loading = false;
	}

	/**
		* On destroy
		*/
	ngOnDestroy(): void {
		// Unsubscribe from all subscriptions
	}

	getCurrentUser() {
			const ts    = this;
			ts.currentUser  = ts._token.getCurrentUser();
			return ts.currentUser;
	}

	upCurrentUser(data: User) {
			this._token.upCurrentUser(data);
	}

}
