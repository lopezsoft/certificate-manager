
import { AfterViewInit, Component, ElementRef, OnInit, ViewChild } from '@angular/core';
import { FormBuilder } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { TranslateService } from '@ngx-translate/core';
import { HttpResponsesService, MessagesService} from '../../utils';

import {FormComponent} from "../../@core/components/forms";
import {ReportsHeaderService} from "../../services/general/reports-header.service";
import TokenService from "../../utils/token.service";

@Component({
  selector: 'app-reports-header',
  templateUrl: './reports-header.component.html',
  styleUrls: ['./reports-header.component.scss']
})
export class ReportsHeaderComponent extends FormComponent implements OnInit, AfterViewInit {
  @ViewChild('uploadFile') uploadFile: ElementRef;
  @ViewChild('imgUp') imgUp: ElementRef;
  @ViewChild('focusElement') focusElement: ElementRef;
  line1: string;
  line2: string;
  foot: string;
  constructor(public fb: FormBuilder,
    public msg: MessagesService,
    public api: HttpResponsesService,
    public router: Router,
    public _token: TokenService,
    public translate: TranslateService,
    public aRouter: ActivatedRoute,
    public reportSer: ReportsHeaderService,
  ) {
    super(fb, msg, api, _token, router, translate, aRouter);
    this.translate.setDefaultLang(this.activeLang);
    this.customForm = this.fb.group({
      line1: ['<p class="ql-align-center">LOPEZSOFT S.A.S </p><p class="ql-align-center">N.I.T: 901.091.403-2 </p><p class="ql-align-center">IVA REGIMEN COMÚN - NO SOMOS GRANDES CONTRIBUYENTES</p><p class="ql-align-center">NO SOMOS AUTORETENEDORES</p>'],
      line2: [''],
      foot: [''],
    });
  }


  ngOnInit(): void {
    super.ngOnInit();
    this.PutURL = '/settings/reports/';
    this.PostURL = '/settings/reports';
  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
    this.loadData();
  }

  loadData(id: any = 0) {
    const frm = this.customForm;
    this.reportSer.getData({}).subscribe((resp) => {
      localStorage.setItem('oldRoute', '/settings');
      this.hideSpinner();
      if (resp.length > 0) {
        this.editing = true;
        const data = resp[0];
        this.uid    = data.id;
        this.line1  = data.line1;
        this.line2  = data.line2;
        this.foot   = data.foot;
        frm.setValue({
          line1: data.line1,
          line2: data.line2,
          foot: data.foot,
        });
        this.imgData = data.image ? `${this.api.getAppUrl()}${data.image}` : '';
      }
    }, () => this.hideSpinner());

  }

  onChangeLine1(value: string) {
    this.customForm.get('line1').setValue(value);
  }

  onChangeLine2(value: string) {
    this.customForm.get('line2').setValue(value);
  }

  onChangeFoot(value: string) {
    this.customForm.get('foot').setValue(value);
  }
}
