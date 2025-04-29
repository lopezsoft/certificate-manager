import { Component, OnInit } from '@angular/core';
import {BaseComponent} from "../@core/components/base/base.component";
import TokenService from "../utils/token.service";
import {Router} from "@angular/router";
import {TranslateService} from "@ngx-translate/core";

@Component({
  selector: 'app-documents',
  template: `
    <router-outlet></router-outlet>
  `
})
export class DocumentsComponent extends BaseComponent implements OnInit {

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
