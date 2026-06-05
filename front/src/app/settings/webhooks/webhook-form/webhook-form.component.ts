import { Component, OnInit, ElementRef, ViewChild, AfterViewInit } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { FormComponent } from 'app/@core/components/forms/form.component';
import { HttpResponsesService, MessagesService } from 'app/utils';
import TokenService from 'app/utils/token.service';
import { WebhooksService } from 'app/services/webhooks';
import { WebhookEndpoint } from 'app/interfaces';
import { WEBHOOK_EVENT_TYPES, WebhookEventType } from 'app/common/enums/WebhookStatus';
import { DebugService } from 'app/utils/debug.service';

/** Grupo visual de eventos para el grid */
interface EventGroup {
  name: string;
  icon: string;
  events: WebhookEventType[];
}

@Component({
    selector: 'app-webhook-form',
    templateUrl: './webhook-form.component.html',
    styleUrls: ['./webhook-form.component.scss'],
    standalone: false
})
export class WebhookFormComponent extends FormComponent implements OnInit, AfterViewInit {

  @ViewChild('nameInput') nameInput!: ElementRef;

  override title = 'Webhook';

  /** Eventos disponibles (objetos con label/value/description/group) */
  eventTypes: WebhookEventType[] = [];
  loadingEvents = true;

  /** Agrupación de eventos para el grid de tarjetas */
  eventGroups: EventGroup[] = [];

  constructor(
    public override fb: FormBuilder,
    public override msg: MessagesService,
    public override api: HttpResponsesService,
    public override _token: TokenService,
    public override router: Router,
    public override translate: TranslateService,
    public override aRouter: ActivatedRoute,
    private webhooksService: WebhooksService,
    private debug: DebugService,
  ) {
    super(fb, msg, api, _token, router, translate, aRouter);
    this.PostURL = '/webhooks';
    this.PutURL = '/webhooks';
  }

  override ngOnInit(): void {
    super.ngOnInit();
    this.buildForm();
    this.loadAvailableEvents();
    
    const id = this.aRouter.snapshot.paramMap.get('id');
    if (id) {
      this.uid = Number(id);
      this.editing = true;
      this.loadData(this.uid);
    }
  }

  override ngAfterViewInit(): void {
    setTimeout(() => {
      if (this.nameInput) {
        this.nameInput.nativeElement.focus();
      }
    }, 100);
  }

  buildForm(): void {
    this.customForm = this.fb.group({
      name: ['', [Validators.required, Validators.maxLength(100)]],
      url: ['', [Validators.required, Validators.pattern(/^https?:\/\/.+/), Validators.maxLength(500)]],
      description: ['', [Validators.maxLength(255)]],
      is_active: [true],
      events: [[], Validators.required],
    });
  }

  // ─── Carga de eventos ──────────────────────────────────────────────────────

  /**
   * Carga los eventos disponibles desde GET /webhooks/events.
   * La respuesta del backend tiene formato: { dataRecords: string[], success: true }
   */
  private loadAvailableEvents(): void {
    this.loadingEvents = true;
    this.webhooksService.getAvailableEvents().subscribe({
      next: (response: any) => {
        const eventValues: string[] = response?.dataRecords ?? response?.data ?? response ?? [];
        this.eventTypes = eventValues.map((value: string) => {
          const known = WEBHOOK_EVENT_TYPES.find(e => e.value === value);
          return known ?? {
            value,
            label: value,
            description: '',
            icon: 'zap',
            group: 'Otros',
          };
        });
        this.buildEventGroups();
        this.loadingEvents = false;
        this.debug.log('WebhookFormComponent', 'Eventos cargados', this.eventTypes);
      },
      error: (err) => {
        this.eventTypes = [...WEBHOOK_EVENT_TYPES];
        this.buildEventGroups();
        this.loadingEvents = false;
        this.debug.warn('WebhookFormComponent', 'Error al cargar eventos del backend, usando fallback local', err);
      }
    });
  }

  /** Construye los grupos visuales a partir de eventTypes */
  private buildEventGroups(): void {
    const groupIconMap: Record<string, string> = {
      'Solicitudes': 'file-text',
      'Certificados': 'award',
      'Pagos': 'credit-card',
      'Otros': 'zap',
    };

    const groupMap = new Map<string, WebhookEventType[]>();
    for (const ev of this.eventTypes) {
      const group = ev.group || 'Otros';
      if (!groupMap.has(group)) {
        groupMap.set(group, []);
      }
      groupMap.get(group)!.push(ev);
    }

    this.eventGroups = Array.from(groupMap.entries()).map(([name, events]) => ({
      name,
      icon: groupIconMap[name] || 'zap',
      events,
    }));
  }

  // ─── Selección de eventos (tarjetas) ───────────────────────────────────────

  /** Eventos actualmente seleccionados (array del form control) */
  get selectedEvents(): string[] {
    return this.customForm.get('events')?.value ?? [];
  }

  isEventSelected(value: string): boolean {
    return this.selectedEvents.includes(value);
  }

  toggleEvent(value: string): void {
    const current = [...this.selectedEvents];
    const idx = current.indexOf(value);
    if (idx >= 0) {
      current.splice(idx, 1);
    } else {
      current.push(value);
    }
    this.customForm.get('events')?.setValue(current);
    this.customForm.get('events')?.markAsTouched();
  }

  allSelected(): boolean {
    return this.eventTypes.length > 0 && this.selectedEvents.length === this.eventTypes.length;
  }

  /** Evento cuyo código fue copiado recientemente (para feedback visual) */
  copiedEvent: string | null = null;

  toggleAllEvents(): void {
    if (this.allSelected()) {
      this.customForm.get('events')?.setValue([]);
    } else {
      this.customForm.get('events')?.setValue(this.eventTypes.map(e => e.value));
    }
    this.customForm.get('events')?.markAsTouched();
  }

  /** Cuenta cuántos eventos de un grupo están seleccionados */
  countSelectedInGroup(group: EventGroup): number {
    return group.events.filter(e => this.isEventSelected(e.value)).length;
  }

  /** Copia el código técnico del evento al portapapeles */
  copyEventCode(value: string, event: Event): void {
    event.stopPropagation(); // Evita toggle de la tarjeta
    
    const setCopiedState = () => {
      this.copiedEvent = value;
      setTimeout(() => {
        if (this.copiedEvent === value) {
          this.copiedEvent = null;
        }
      }, 1800);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(setCopiedState).catch(err => console.error('Error copying text: ', err));
    } else {
      // Fallback para HTTP local o navegadores sin Clipboard API
      const textArea = document.createElement('textarea');
      textArea.value = value;
      // Evitar scroll
      textArea.style.top = '0';
      textArea.style.left = '0';
      textArea.style.position = 'fixed';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      try {
        if (document.execCommand('copy')) {
          setCopiedState();
        }
      } catch (err) {
        console.error('Fallback copy failed', err);
      }
      document.body.removeChild(textArea);
    }
  }

  // ─── CRUD ──────────────────────────────────────────────────────────────────

  override loadData(id: any = 0): void {
    super.loadData(id);
    this.webhooksService.getById(Number(id)).subscribe({
      next: (resp: any) => {
        const wh: WebhookEndpoint = resp?.dataRecords ?? resp;
        this.customForm.patchValue({
          name: wh.name ?? '',
          url: wh.url,
          description: wh.description ?? '',
          is_active: wh.is_active ?? true,
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
      name: value.name,
      url: value.url,
      description: value.description || undefined,
      is_active: value.is_active,
      events: value.events,
    };

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

