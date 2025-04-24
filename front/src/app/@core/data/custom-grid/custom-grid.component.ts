import {AfterViewInit, Injectable, OnInit, ViewChild} from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
// Services
import {HttpResponsesService, MessagesService} from '../../../utils';
// Base component
import { BaseComponent } from '../../components/base/base.component';
import {ColumnContract} from 'exodolibs/lib/components/grid/contracts';
import {ExodoGridComponent} from 'exodolibs';
import TokenService from "../../../utils/token.service";
@Injectable()
export class CustomGridComponent extends BaseComponent implements OnInit, AfterViewInit {
	@ViewChild('exodoGrid', { static: false }) exodoGrid: ExodoGridComponent;
	showEditButton = true;
	showDeleteButton = true;
	useNewButton = true;
	public eColumns: ColumnContract[] = [
		{
			text: '#',
			dataIndex: '#',
			width: '16px',
			align: 'right',
			cellRender: (row, rowIndex): string => {
				return '<b>' + (rowIndex + 1).toString() + '</b>';
			}
		}
	];

  public title              = 'Encabezado del grid';
  public useImport          = false;
  public useOtherButton     = false;
  public textOtherButton    = 'Texto del botón';
  public faOtherButton      = 'fa fa-upload mr-1 fas-fa-22';
  public active             = 1;
  public crudApi: {
    create: string,
    read: string,
    update: string,
    delete: string,
		params?: any
  };
  columns: ColumnContract[]      = [];
  constructor(public msg: MessagesService,
              public api: HttpResponsesService,
			  public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
  ) {
    super(_token, router, translate);
  }
	ngOnInit(): void {
		this.combineColumns();
	}
	combineColumns() {
	  	if (this.showEditButton) {
			this.eColumns = [...this.eColumns, {
				text: '',
				dataIndex: '#edit#',
				width: '16px',
				cellRender: (): string => {
					return `<span class="span-button" title="Editar">
            <i class="fas fa-edit fa-cursor fas-fa-edit"></i>
          </span>`;
				},
				cellClick: (row: any): void => {
					this.editData(row);
				}
			}];
		}
		if (this.showDeleteButton) {
			this.eColumns = [...this.eColumns, {
				text: '',
				dataIndex: '#delete#',
				width: '16px',
				cellRender: (): string => {
					return `<span class="span-button" title="Eliminar">
            <i class="fas fa-trash-alt fa-cursor fas-fa-delete"></i>
          </span>`;
				},
				cellClick: (row: any): void => {
					this.deleteData(row);
				}
			}];
		}
		this.eColumns = [...this.eColumns, ...this.columns];
	}
	ngAfterViewInit(): void {
		const params          = this.crudApi?.params ?? {};
		this.exodoGrid.proxy.api = {
			read: `${this.api.getUrl()}${this.crudApi.read}`,
		};
		this.exodoGrid.onLoad({
			...params
		});
	}
  editData(data: any): void {
    // Implements
    this.saveRoute();
  }
  deleteData(data: any): void {
    const ts    = this;
    const lang  = ts.translate;
    // Implements
		this.msg.confirm(lang.instant('titleMessages.delete'), lang.instant('bodyMessages.delete'))
			.then((result) => {
				if (result.value) {
					ts.exodoGrid.isLoading = true;
					ts.api.delete(`${ts.crudApi.delete}${data.uid || data.id}`)
						.subscribe({
							next: () => {
								ts.exodoGrid.isLoading = false;
								ts.exodoGrid.searchQuery(ts.exodoGrid.getSearchFieldValue());
							},
							error:  (err: string) => {
								ts.exodoGrid.isLoading = false;
								ts.msg.errorMessage(lang.instant('general.error'), err);
							}
					});
      }
    });
  }

  createData(): void {
    // Implements
    this.saveRoute();
  }

  importData(): void {
    // Implements
    localStorage.setItem('oldRoute', this.router.url);
  }

  onOtherButton(): void {
    // Implements
    localStorage.setItem('oldRoute', this.router.url);
  }

  saveRoute(): void {
    localStorage.setItem('oldRoute', this.router.url);
  }
	goToParent(): void {
		const currentUrl = this.router.url;
		const parentUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/'));
		this.router.navigateByUrl(parentUrl);
	}
}
