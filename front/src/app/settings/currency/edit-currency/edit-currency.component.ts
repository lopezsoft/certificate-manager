import { Component, OnInit, ElementRef, ViewChild } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';

import { TranslateService } from '@ngx-translate/core';
import { FormComponent } from '../../../@core/components/forms';
import {Currency, CurrencySys} from '../../../models/general-model';
import { HttpResponsesService, MessagesService } from '../../../utils';
import TokenService from '../../../utils/token.service';
import { CurrencyService } from '../../../services/general/currency.service';
import { CurrencySysService } from '../../../services/general/currency-sys.service';
import { ErrorResponse } from '../../../interfaces';

@Component({
  selector: 'app-edit-currency',
  templateUrl: './edit-currency.component.html'
})
export class EditCurrencyComponent extends FormComponent implements OnInit{

  @ViewChild('focusElement') focusElement!: ElementRef;
  @ViewChild('exchangeRateValue') exchangeRateValue!: ElementRef;
  currency: Currency[]= [];
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              private curr: CurrencyService,
              private currSys: CurrencySysService,
  ){
    super(fb, msg, api, _token, router, translate, aRouter);
    this.customForm = this.fb.group({
      currency_id                 : [272, [Validators.required] ],
      exchange_rate_value         : [1, [Validators.required] ],
      national_currency           : [false],
      plural_name                 : ['PESOS', [Validators.required, Validators.minLength(3)] ],
      singular_name               : ['PESO', [Validators.required, Validators.minLength(3)] ],
      denomination                : ['MCTE'],
    });
  }

  ngOnInit(): void {
    super.ngOnInit();
    const lang  = this.translate;
    this.title    = `${lang.instant('general.createEdit')} ${lang.instant('currency.title')}`;
    this.PutURL   = '/currency/';
    this.PostURL  = '/currency';
    this.showSpinner();
    this.curr.getData({}).subscribe((resp) => {
      this.currency  = resp;
      this.hideSpinner();
    });

  }

  loadData(id: any = 0): void {
    super.loadData();
    const frm   = this.customForm;
    this.editing  = true;

    this.currSys.getData({uid: id}).subscribe((resp) => {
      const data = resp[0];
      frm.setValue({
        currency_id                 : data.currency_id        ,
        exchange_rate_value         : data.exchange_rate_value,
        national_currency           : data.national_currency  ,
        plural_name                 : data.plural_name        ,
        singular_name               : data.singular_name      ,
        denomination                : data.denomination       ,
      });
      this.fullLoad();
    });
  }

  onCurrencyChange(id: any): void{
    const frm = this.customForm;
    if(id){
      const curr  = this.currency.find( currency => currency.id === id);
      const local = this.customForm.get('national_currency')?.value;
      if (!local){
        this.showSpinner('Cargando tasa de cambio');
        frm.get('plural_name')?.setValue(curr?.CurrencyName);
        frm.get('singular_name')?.setValue(curr?.Money);
        frm.get('exchange_rate_value')?.setValue(0);
        this.currSys.getChangeLocal({ source: curr?.CurrencyISO}).
          subscribe((resp) => {
            this.hideSpinner();
            frm.get('exchange_rate_value')?.setValue(resp[0].value);
          },( Err: ErrorResponse) => {
            this.hideSpinner();
            // this.msg.errorMessage('', Err.error.message);
          });
      }
    }
  }
}
