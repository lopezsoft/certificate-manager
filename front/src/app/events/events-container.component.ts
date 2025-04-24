import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import {BaseComponent} from "../@core/components/base/base.component";
import TokenService from "../utils/token.service";

@Component({
  selector: 'app-shopping-container',
  templateUrl: './events-container.component.html',
  styleUrls: ['./events-container.component.scss']
})
export class EventsContainerComponent extends BaseComponent implements OnInit {

  constructor(
    public router: Router,
    public _token: TokenService,
    public translate: TranslateService,
  ) {
      super(_token, router, translate);
  }

  ngOnInit(): void {
  }

}
