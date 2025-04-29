import { Component, OnInit,} from '@angular/core';
import {Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';

import TokenService from '../utils/token.service';
import {BaseComponent} from "../@core/components/base/base.component";

@Component({
  selector: 'app-profile-container',
  templateUrl: './documents.component.html',
  styleUrls: ['./documents.component.scss']
})
export class DocumentsContainerComponent extends BaseComponent implements OnInit {

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
