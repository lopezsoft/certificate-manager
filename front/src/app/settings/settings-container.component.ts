import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { BaseComponent } from '../@core/components/base/base.component';
import TokenService from '../utils/token.service';

@Component({
  selector: 'app-settings-container',
  templateUrl: './settings.component.html',
  styleUrls: ['./settings.component.scss']
})
export class SettingsContainerComponent extends BaseComponent implements OnInit {

  constructor(
    public _token: TokenService,
		public router: Router,
		public translate: TranslateService,
  ) {
    super(_token, router, translate);
  }

  ngOnInit(): void {
    super.ngOnInit();
  }

  goRoute(name: string): void {
    super.goRoute(name);
  }

}
