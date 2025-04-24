import { Component, OnInit, AfterViewInit, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { HttpResponsesService, MessagesService } from '../../utils';
import TokenService from '../../utils/token.service';
import {ExodoGridComponent} from "exodolibs";
import {CustomGridComponent} from "../../@core/data/custom-grid/custom-grid.component";
import {Resolutions} from "../../models/general-model";

@Component({
  selector: 'app-resolutions',
  templateUrl: 'resolutions.component.html',
})
export class ResolutionsComponent extends CustomGridComponent implements OnInit, AfterViewInit{

  @ViewChild('exodoGrid', { static: false }) exodoGrid: ExodoGridComponent;
  title = 'Resoluciones de facturación.';

  constructor(public msg: MessagesService,
    public api: HttpResponsesService,
    public _token: TokenService,
    public router: Router,
    public translate: TranslateService,
    public aRouter: ActivatedRoute,
  ) {
    super(msg, api, _token, router, translate, aRouter);
  }

  ngOnInit(): void {
    this.columns =
        [
          { text:  'Documento',
            dataIndex: 'voucher_name',
            cellRender: (row: Resolutions): string => {
              return row.type_document.voucher_name.toUpperCase();
            }
          },
          { text:  'Nombre del documento', dataIndex: 'invoice_name', width: '100%' },
          { text:  'Nº. Resolución', align: 'right', dataIndex: 'resolution_number', width: '120px' },
          { text:  'Prefijo', dataIndex: 'prefix', width: '110px' },
          { text:  'Iniciar desde', align: 'right', dataIndex: 'initial_number', width: '110px' },
          { text:  'Rango desde', align: 'right', dataIndex: 'range_from', width: '110px' },
          { text:  'Rango hasta', align: 'right', dataIndex: 'range_up', width: '110px'},
          { text:  'Activa', dataIndex: 'active', minWidth: '85px', type: 'boolean'},
        ];
    super.ngOnInit();
    this.crudApi = {
      create: '/resolutions',
      read:   '/resolutions',
      update: '/resolutions/',
      delete: '/resolutions/',
      params: {
        active: 1,
      }
    };
  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
  }

  createData(): void {
    const ts = this;
    super.createData();
    ts.goRoute('settings/resolutions/create');
  }

  editData(data: any): void {
    super.editData(data);
    this.goRoute(`settings/resolutions/edit/${data.id}`);
  }
  
  
  
  onCheckActive(event: any): void {
    this.exodoGrid.onLoad({
      active: event.target.checked ? 0 : 1
    });
  }
}
