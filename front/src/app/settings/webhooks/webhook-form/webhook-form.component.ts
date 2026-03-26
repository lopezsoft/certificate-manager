import { Component, OnInit } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { FormComponent } from 'app/@core/components/forms/form.component';
import { HttpResponsesService, MessagesService } from 'app/utils';
import TokenService from 'app/utils/token.service';
import { WebhooksService } from 'app/services/webhooks';
import { WebhookEndpoint } from 'app/interfaces';
import { WEBHOOK_EVENT_TYPES } from 'app/common/enums/WebhookStatus';

@Component({
  selector: 'app-webhook-form',
  templateUrl: './webhook-form.component.html',
  styleUrls: ['./webhook-form.component.scss']
})
export class WebhookFormComponent extends FormComponent implements OnInit {

  override title = 'Webhook';
  eventTypes = WEBHOOK_EVENT_TYPES;

  constructor(
    public override fb: FormBuilder,
    public override msg: MessagesService,
    public override api: HttpResponsesService,
    public override _token: TokenService,
    public override router: Router,
    public override translate: TranslateService,
    public override aRouter: ActivatedRoute,
    private webhooksService: WebhooksService,
  ) {
    super(fb, msg, api, _token, router, translate, aRouter);
    this.PostURL = '/webhooks';
    this.PutURL = '/webhooks';
  }

  override ngOnInit(): void {
    super.ngOnInit();
    this.buildForm();
  }

  override ngAfterViewInit(): void {
    const id = this.aRouter.snapshot.paramMap.get('id');
    if (id) {
      this.uid = Number(id);
      this.editing = true;
      this.loadData(this.uid);
    }
  }

  buildForm(): void {
    this.customForm = this.fb.group({
      url: ['', [Validators.required, Validators.pattern(/^https?:\/\/.+/)]],
      description: [''],
      secret: [''],
      events: [[], Validators.required],
    });
  }

  override loadData(id: any = 0): void {
    super.loadData(id);
    this.webhooksService.getById(Number(id)).subscribe({
      next: (wh: WebhookEndpoint) => {
        this.customForm.patchValue({
          url: wh.url,
          description: wh.description ?? '',
          events: wh.events,
        });
        this.fullLoad();
      },
      error: () => {
        this.msg.toastMessage('Error', 'No se pudo cargar el webhook.', 3);
        this.fullLoad();
      },
    });
  }

  onSubmit(): void {
    if (this.customForm.invalid) {
      this.customForm.markAllAsTouched();
      return;
    }

    const value = this.customForm.value;
    const payload: any = {
      url: value.url,
      description: value.description || undefined,
      events: value.events,
    };

    if (!this.editing && value.secret) {
      payload['secret'] = value.secret;
    }

    const request$ = this.editing
      ? this.webhooksService.update(this.uid, payload)
      : this.webhooksService.create(payload);

    this.showSpinner();
    request$.subscribe({
      next: () => {
        this.hideSpinner();
        this.msg.toastMessage(
          'Webhook',
          this.editing ? 'Webhook actualizado correctamente.' : 'Webhook creado correctamente.',
          1,
        );
        this.router.navigate(['/settings/webhooks']);
      },
      error: () => {
        this.hideSpinner();
        this.msg.toastMessage('Error', 'No se pudo guardar el webhook.', 3);
      },
    });
  }

  cancel(): void {
    this.router.navigate(['/settings/webhooks']);
  }
}
