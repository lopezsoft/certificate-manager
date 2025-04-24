import {AfterViewInit, Component, OnInit, ViewChild} from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { ActivatedRoute, Router } from '@angular/router';
// Services
import { HttpResponsesService, MessagesService } from '../../utils';
// Base component
import TokenService from '../../utils/token.service';
import { Software } from '../../models/general-model';
import {GlobalSettingsService} from "../../services/global-settings.service";
import {interval} from "rxjs";
import {SoftwareService} from "../../services/general/software.service";
import {BaseComponent} from "../../@core/components/base/base.component";
import {LoadMaskService} from "../../services/load-mask.service";
import {SearchDataComponent} from "../../common/components/search-data/search-data.component";
import {ExodoPaginationComponent} from "exodolibs";
import {DocumentViewComponent} from "../../documents/document-view/document-view.component";
@Component({
  selector: 'app-software',
  templateUrl: './software.component.html',
  styleUrls: ['./software.component.scss']
})
export class SoftwareComponent extends BaseComponent  implements OnInit, AfterViewInit {
  @ViewChild('searchItems') searchItems: SearchDataComponent;
  @ViewChild('pagination') pagination: ExodoPaginationComponent;
  @ViewChild('documentView') documentView: DocumentViewComponent;
  protected isClicked = false;
  protected title = 'Software';
  constructor(public msg: MessagesService,
    public api: HttpResponsesService,
    public _token: TokenService,
    public router: Router,
    public translate: TranslateService,
    public aRouter: ActivatedRoute,
    public settings: GlobalSettingsService,
    public softwareS: SoftwareService,
  public mask: LoadMaskService
  ) {
    super(_token, router, translate);
  }
  ngOnInit(): void {
    super.ngOnInit();
  }

  ngAfterViewInit(): void {
    this.softwareS.currentSoftware = null;
    this.onSearch();
  }

  protected runEnableVerification() {
    if(this.settings.isSoftwareEnabled) {
      this.onSearch();
      interval(1000 * 15).subscribe(() => {
        if(this.settings.isSoftwareEnabled) {
          this.onSearch();
        }
      });
    }
  }

  protected onSearch(): void {
    this.isClicked = false;
    if (!this.settings.isSoftwareEnabled) {
      this.mask.showBlockUI();
    }
    this.softwareS.getData()
        .subscribe({
            next: (data) => {
                this.mask.hideBlockUI();
                if (this.settings.isSoftwareEnabled && this.softwareS.currentSoftware) {
                  this.softwareS.currentSoftware = data.find( (row) => row.id === this.softwareS.currentSoftware.id);
                  if (this.softwareS.currentSoftware) {
                      this.softwareS.currentSoftware.checked = true;
                  }
                }
            },
            error: () => {
                this.mask.hideBlockUI();
            }
        })
  }

  protected onRefreshPagination() {
    this.onSearch();
  }

  protected onClickItem(row: Software): void {
    if (this.softwareS.currentSoftware) {
      this.softwareS.currentSoftware.checked = false;
    }
    row.checked = true;
    this.softwareS.currentSoftware = row;
    this.softwareS.currentSoftware.checked = true;
    this.isClicked = true;
    this.documentView.initData();
  }

  protected toggleNavbar() {
    this.isClicked = false;
    const currentProduct  = this.softwareS.data.find((row) => row.checked);
    currentProduct.checked = false;
    this.softwareS.currentSoftware = null;
  }

  protected getTooltip (row: Software): string {
    let title = 'Desconocido';
    const errors = row?.test_process?.error_message;
    if (errors) {
      title = errors['string'];
    }
    if(row.test_process?.StatusDescription && !title) {
        title = row.test_process?.StatusDescription;
    }
    if (!title) {
        title = errors as any || 'Desconocido';
    }
    return title;
  }

  protected getProcessStatue(item: Software) {
    if (item.type_id?.toString() == '3' || item.environment.id == 1) {
        return item.environment.id == 1 ? 'FINISHED' : 'UNKNOWN';
    }
    return item?.test_process?.status || 'UNKNOWN';
  }

  protected getEnvironment(item: Software) {
    if (item.type_id?.toString() == '3' || item.environment.id == 1) {
        return item.environment.environment_name
    }
    return item?.test_process?.status ? item.environment.environment_name : 'Desconocido';
  }

  protected getStatusDescription(item: Software) {
    if (item.type_id?.toString() == '3' || item.environment.id == 1) {
        return item.environment.id == 1 ? 'FINALIZADO' : 'Desconocido';
    }
    return item?.test_process?.status_description ?? 'Desconocido';
  }
}
