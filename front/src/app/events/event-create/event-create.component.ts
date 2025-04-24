import {AfterViewInit, Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {FormBuilder} from '@angular/forms';
import {HttpResponsesService, MessagesService} from '../../utils';
import {ActivatedRoute, Router} from '@angular/router';
import {TranslateService} from '@ngx-translate/core';
import {EventsService} from '../../services/events/events.service';
import {FormComponent} from "../../@core/components/forms";
import TokenService from "../../utils/token.service";

@Component({
  selector: 'app-event-create',
  templateUrl: './event-create.component.html',
  styleUrls: ['./event-create.component.scss']
})
export class EventCreateComponent extends FormComponent implements OnInit, AfterViewInit {
  @ViewChild('trackIdInput')  trackIdInput: ElementRef;
  title = 'Importar documentos recepcionados';
  trackId: string;
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              public eventService: EventsService,
  ) {
    super(fb, msg, api, _token, router, translate, aRouter);
  }
  ngOnInit(): void {
    super.ngOnInit();
  }
  ngAfterViewInit(): void {
    this.trackIdInput.nativeElement.focus();
  }

  import() {
    const ts = this;
    if (ts.trackId.length === 0) {
      ts.msg.toastMessage('', 'Debe ingresar un cufe/cude del documento a importar.', 4);
      return;
    }
    ts.showSpinner('Importando documento.');
    ts.eventService.eventsImportTrackId(ts.trackId)
      .subscribe({
        next: () => {
          ts.msg.toastMessage('', 'Se ha importado correctamente el documento.');
          ts.hideSpinner();
          this.trackId = '';
          this.trackIdInput.nativeElement.focus();
        },
        error: () => {
          ts.hideSpinner();
        }
      });
  }

  goToParent() {
    this.router.navigate(['/events/reception']);
  }
}
