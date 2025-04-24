import { Component, OnInit, AfterViewInit, ViewChild, ElementRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { HttpResponsesService, MessagesService } from '../../utils';
import TokenService from '../../utils/token.service';
import {CustomGridComponent} from "../../@core/data/custom-grid/custom-grid.component";
import {ExodoGridComponent} from "exodolibs";
import {CurrencySys} from "../../models/general-model";

@Component({
  selector: 'app-currency',
  templateUrl: '../../shared/global-grid.component.html',
})
export class CurrencyComponent extends CustomGridComponent implements OnInit, AfterViewInit{
  @ViewChild('exodoGrid', { static: false }) exodoGrid: ExodoGridComponent;
  constructor(public msg: MessagesService,
    public api: HttpResponsesService,
    public _token: TokenService,
    public router: Router,
    public translate: TranslateService,
    public aRouter: ActivatedRoute,
  ) {
    super(msg, api, _token, router, translate, aRouter);
    this.translate.setDefaultLang(this.activeLang);
  }

  ngOnInit(): void {
    this.changeLanguage(this.activeLang);
    this.title  = this.translate.instant('currency.title');
    this.changeLanguage(this.activeLang);
    const ts   = this;
    const lang = ts.translate;
    ts.columns =
        [
          {
            text: lang.instant('currency.name') || 'Moneda',
            dataIndex: 'CurrencyName',
            width: '100%',
            cellRender: (row: CurrencySys): string => {
              return row.currency.CurrencyName.toUpperCase();
            }
          },
          { text: lang.instant('currency.pluralName')   || 'Nombre plural', dataIndex: 'plural_name' },
          { text: lang.instant('currency.singularName') || 'Nombre singular', dataIndex: 'singular_name' },
          { text: lang.instant('currency.denomination') || 'Denominación', dataIndex: 'denomination' },
        ];
    this.title  = this.translate.instant('currency.title');
    super.ngOnInit();
    ts.crudApi = {
      create: '/currency',
      read:   '/currency',
      update: '/currency/',
      delete: '/currency/'
    };
  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
  }

  createData(): void {
    super.createData();
    this.goRoute('settings/currency/create');
  }

  editData(data: any): void {
    super.editData(data);
    this.goRoute(`settings/currency/edit/${data.id}`);
  }
}
