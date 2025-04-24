import {AfterViewInit, Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {FormBuilder, Validators} from '@angular/forms';
import {HttpResponsesService, MessagesService} from '../../utils';
import {ActivatedRoute, Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';
import {IdentityDocuments} from '../../models/general-model';
import {VerificationDigit} from '../../common/verification-digit';
import {DocumentReceptionPerson} from '../../interfaces/events';
import {FormComponent} from "../../@core/components/forms";
import TokenService from "../../utils/token.service";
import {CrudTableService} from "../../services/crud-table.service";
import {DocumentsService} from "../../services/general";

@Component({
  selector: 'app-event-setting',
  templateUrl: './event-setting.component.html',
  styleUrls: ['./event-setting.component.scss']
})
export class EventSettingComponent extends FormComponent implements OnInit, AfterViewInit {
  @ViewChild('focusElement') focusElement: ElementRef;
  title = 'Ajustes de eventos';
  identityDocs: IdentityDocuments[] = [];
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              public table: CrudTableService,
              private documentSer: DocumentsService,
  ) {
    super(fb, msg, api, _token, router, translate, aRouter);
    this.translate.setDefaultLang(this.activeLang);
    this.customForm = this.fb.group({
      identity_document_id: [3, [Validators.required]],
      dni   : ['', [Validators.required, Validators.minLength(2)]],
      dv    : [''],
      email_reception    : [''],
      email : ['', [Validators.required, Validators.email]],
      first_name: ['', [Validators.required, Validators.minLength(2)]],
      last_name : ['', [Validators.required, Validators.minLength(2)]],
      job_title : ['', [Validators.required, Validators.minLength(2)]],
      department: ['', [Validators.required, Validators.minLength(2)]],
      send_events: [true],
    });
  }
  ngOnInit(): void {
    super.ngOnInit();
    const ts    = this;
    ts.title    = 'Ajustes de eventos';
    ts.PutURL   = '/crud/';
    ts.PostURL  = '/crud';
    ts.queryParams = {
      tbPrefix: 'T005'
    };

    ts.documentSer.getIdentityDocuments({}).subscribe((resp) => {
      ts.identityDocs = resp;
    });
  }
  ngAfterViewInit() {
    super.ngAfterViewInit();
    this.focusElement.nativeElement.focus();
    this.loadData();
  }
  loadData(id: any = 0) {
    super.loadData(id);
    const ts    = this;
    const frm   = ts.customForm;
    const params = {
      tbPrefix: 'T005'
    };
    localStorage.setItem('oldRoute', '/events');
    ts.table.getData(params)
      .subscribe({
        next: (resp: any) => {
          const data: DocumentReceptionPerson = resp.data[0];
          this.hideSpinner();
          if (!data) {
            return;
          }
          this.editing  = true;
          this.uid      = data.id;
          frm.setValue({
            identity_document_id: data.identity_document_id,
            dni   : data.dni,
            dv    : data.dv,
            email_reception    : data.email_reception,
            email : data.email,
            first_name: data.first_name,
            last_name : data.last_name,
            job_title : data.job_title,
            department: data.department,
            send_events: data.send_events,
          });
        },
        error: () => {
          this.hideSpinner();
        }
      });
  }
  onChangeDni(value: any) {
    const dv = VerificationDigit.getDigit(value);
    this.customForm.get('dv').setValue(dv);
  }
}