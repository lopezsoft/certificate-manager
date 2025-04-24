import {AfterViewInit, Component, OnInit, ViewChild} from '@angular/core';
import {HttpResponsesService, MessagesService} from "../utils";
import TokenService from "../utils/token.service";
import {ActivatedRoute, Router} from "@angular/router";
import {TranslateService} from "@ngx-translate/core";
import {CustomGridComponent} from "../@core/data/custom-grid/custom-grid.component";
import {ExodoGridComponent} from "exodolibs";
import {Company} from "../models/companies-model";

@Component({
  selector: 'app-customers',
  templateUrl: '../shared/global-grid.component.html',
  styleUrls: ['./customers.component.scss']
})
export class CustomersComponent extends CustomGridComponent implements OnInit, AfterViewInit{
  @ViewChild('exodoGrid', { static: false }) exodoGrid: ExodoGridComponent;
  title = 'Clientes.';
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
          { text:  'Empresa',
            dataIndex: 'company_name',
            cellRender: (row: Company): string => {
              const name  = row.company_name.trim();
              const doc   = row.dni;
              const dv    = row.dv;
              return `<div class="d-flex align-items-center">
                <div class="avatar mr-1 ml-0 ${row.active ? 'bg-light-success' : 'bg-light-danger'}">
                    <div class="avatar-content">${name.charAt(0)}${name.charAt(1)}</div>
                </div>
                 <div class="cell-line-height">
                  <span class="font-weight-bold d-block text-nowrap font-small">${name}</span>
                  <span class="text-muted font-small-3"> ${doc}-<b>${dv}</b> </span>
                </div>
            </div>`;
            }
          },
          { text:  'Dirección', dataIndex: 'address', width: '100%' },
          { text:  'Email',  dataIndex: 'email', width: '100%' },
          { text:  'Teléfono', dataIndex: 'phone', width: '110px' },
          { text:  'Celular', dataIndex: 'mobile', width: '110px' },
          { text:  'Activa', dataIndex: 'active', minWidth: '85px', type: 'boolean'},
        ];
    super.ngOnInit();
    this.crudApi = {
      create: '/auth/signup',
      read:   '/company/customers',
      update: '/company/',
      delete: '/company/customer/',
    };
  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
  }

  createData(): void {
    super.createData();
    this.goRoute('customers/create');
  }

  editData(data: any): void {
    super.editData(data);
    this.goRoute(`customers/edit/${data.id}`);
  }
}
