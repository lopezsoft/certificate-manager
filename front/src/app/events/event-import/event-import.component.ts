import {Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {FormBuilder} from '@angular/forms';
import {HttpResponsesService, MessagesService} from '../../utils';
import {ActivatedRoute, Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';
import {EventsService} from '../../services/events/events.service';
import {FormComponent} from "../../@core/components/forms";
import TokenService from "../../utils/token.service";

@Component({
  selector: 'app-event-import',
  templateUrl: './event-import.component.html',
  styleUrls: ['./event-import.component.scss']
})
export class EventImportComponent extends FormComponent implements OnInit {

  @ViewChild('fileUpload', { static: false}) fileUpload: ElementRef;
  files = [];
  formData: FormData;
  title = 'Importar documentos recepcionados';
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public router: Router,
              public _token: TokenService,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              public eventService: EventsService,
  ) {
    super(fb, msg, api, _token, router, translate, aRouter);
  }

  ngOnInit(): void {
    super.ngOnInit();
  }

  saveAndClose() {
    const ts = this;
    if (ts.files.length === 0) {
      ts.msg.toastMessage('', 'Debe seleccionar un archivo.', 4);
      return;
    }
    ts.showSpinner('Importando listado.');
    ts.eventService.eventsImportExcel(ts.formData)
      .subscribe({
        next: () => {
          this.fileUpload.nativeElement.value = '';
          ts.msg.toastMessage('', 'Se ha importado correctamente el listado de los documentos.')
          ts.hideSpinner();
          ts.goRoute('events/reception');
        },
        error: () => {
          ts.hideSpinner();
        }
      });
  }
  onClick(e: any) {
    const ts = this;
    this.files = [];
    const fileUpload = this.fileUpload.nativeElement;
    const file = fileUpload.files[0];
    if (file.type !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
      ts.fileUpload.nativeElement.value = '';
      ts.msg.toastMessage('', 'Formato de archivo incorrecto', 4);
    } else {
      this.files.push({ data: file, inProgress: false, progress: 0});
      this.uploadFiles(this.files);
    }
  }

  uploadFiles(file: any) {
    const ts = this;
    ts.formData = null;
    ts.formData = new FormData();
    ts.formData.append('file', file[0].data);
    file.inProgress = true;
  }
}
