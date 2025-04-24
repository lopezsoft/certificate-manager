import { Component, OnInit, ElementRef, ViewChild, AfterViewInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';

import { FormComponent } from '../../@core/components/forms';
import { Currency } from '../../models/general-model';
import { AccountingDocuments } from '../../models/accounting-model';
import { HttpResponsesService, MessagesService } from '../../utils';
import TokenService from '../../utils/token.service';
import { ResolutionsService } from '../../services/general/resolutions.service';

@Component({
  selector: 'app-resolution-form',
  templateUrl: './resolution-form.component.html',
  styleUrls: ['./resolution-form.component.scss']
})
export class ResolutionFormComponent extends FormComponent implements OnInit, AfterViewInit{
  @ViewChild('focusElement') focusElement!: ElementRef;
  @ViewChild('exchangeRateValue') exchangeRateValue!: ElementRef;
  footerLine1: string;
  footerLine2: string;
  footerLine3: string;
  footerLine4: string;
  currency: Currency[]= [];
  aDocuments: AccountingDocuments[]= [];
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              private resSer: ResolutionsService,
  ){
    super(fb, msg, api, _token, router, translate, aRouter);
    this.translate.setDefaultLang(this.activeLang);
    this.customForm = this.fb.group({
      date_from         : ['2019-01-19', [Validators.required]],
      date_up           : ['2030-01-19', [Validators.required]],
      footline4         : ['<div style="text-align: center;"></div>'],
      footline2         : [''],
      footline3         : [''],
      footline1         : [''],
      headerline1       : [''],
      headerline2       : [''],
      initial_number    : [1, [Validators.required]],
      invoice_name      : ['FACTURA ELECTRÓNICA DE VENTA', [Validators.required]],
      prefix            : ['SETP'],
      range_from        : ['1', [Validators.required]],
      range_up          : ['1000', [Validators.required]],
      resolution_number : ['18760000001'],
      type_document_id  : [7, [Validators.required]],
      active            : [1],
      technical_key     : [''],
    });
  }


  ngOnInit(): void {
    super.ngOnInit();
    const ts    = this;
    ts.PutURL   = '/resolutions/';
    ts.PostURL  = '/resolutions';

    const param = {
      where : '{"active":"1"}',
      limit : 30
    };

    ts.resSer.getAccountingDocuments(param).subscribe((resp) => {
      ts.aDocuments  = resp;
    });

  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
    const ts = this;
    if(!ts.uid) {
      ts.onEditorValue();
    }
  }

  loadData(id: any = 0): void {
    super.loadData();
    const ts    = this;
    const frm   = ts.customForm;
    ts.editing  = true;
    ts.resSer.getData({uid: id}).subscribe((resp) => {
      const data = resp[0];
      frm.setValue({
        date_from         : data.date_from,
        date_up           : data.date_up,
        footline1         : data.footline1,
        footline2         : data.footline2,
        footline3         : data.footline3,
        footline4         : data.footline4,
        headerline1       : data.headerline1,
        headerline2       : data.headerline2,
        initial_number    : data.initial_number,
        invoice_name      : data.invoice_name,
        prefix            : data.prefix,
        range_from        : data.range_from,
        range_up          : data.range_up,
        active            : data.active,
        resolution_number : data.resolution_number,
        type_document_id  : data.type_document_id,
        technical_key     : data.technical_key
      });
      ts.fullLoad();
      ts.onEditorValue();
    });
  }

  onResetForm(form: FormGroup): void {
    super.onResetForm(form);
    this.customForm.setValue({
      date_from         : '2019-01-19',
      date_up           : '2030-01-19',
      footline4         : '<div style="text-align: center;"></div>',
      footline2         : '',
      footline3         : '',
      footline1         : '',
      headerline1       : [''],
      headerline2       : [''],
      initial_number    : '1',
      invoice_name      : 'FACTURA ELECTRÓNICA DE VENTA',
      prefix            : 'SETP',
      range_from        : '1',
      range_up          : '1000',
      resolution_number : '18760000001',
      active            : 1,
      type_document_id  : 7,
      technical_key     : ''
    });
    this.onEditorValue();
  }
  onEditorValue() {
    const ts    = this;
    const frm   = ts.customForm;
    ts.footerLine1 = frm.get('footline1')?.value;
    ts.footerLine2 = frm.get('footline2')?.value;
    ts.footerLine3 = frm.get('footline3')?.value;
    ts.footerLine4 = frm.get('footline4')?.value;
  }
  onChangeFooterLine1(value: any) {
    this.customForm.get('footline1').setValue(value);
  }
  onChangeFooterLine2(value: any) {
    this.customForm.get('footline2').setValue(value);
  }
  onChangeFooterLine3(value: any) {
    this.customForm.get('footline3').setValue(value);
  }
  onChangeFooterLine4(value: any) {
    this.customForm.get('footline4').setValue(value);
  }
  
  onChangedTypeDocument(id: number) {
    const document = this.aDocuments.find((doc) => doc.id === id);
    if (document) {
      this.customForm.get('invoice_name')?.setValue(document.voucher_name.toUpperCase());
    }
  }
}
