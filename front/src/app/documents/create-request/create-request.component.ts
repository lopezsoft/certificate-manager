import { AfterViewInit, Component, ElementRef, inject, OnInit, ViewChild } from '@angular/core';
import { Cities, IdentityDocuments, TypeOrganzation, EntityDocumentType, Country } from "../../models/general-model";
import { FormBuilder, FormGroup, Validators } from "@angular/forms";
import { HttpResponsesService, MessagesService } from "../../utils";
import { ActivatedRoute, Router } from "@angular/router";
import { CompanyService } from "../../services/companies";
import { CitiesService, CountriesService, DocumentsService } from "../../services/general";
import { LoadMaskService } from "../../services/load-mask.service";
import TokenService from "../../utils/token.service";
import { FileUploadConfig } from "../../shared/components/file-upload/file-upload-config.interface";
import { FileUploadData } from "../../shared/components/file-upload/file-upload.component";
import { HttpErrorResponse } from "@angular/common/http";
import { DebugService } from "../../utils/debug.service";

@Component({
  selector: 'app-create-request',
  templateUrl: './create-request.component.html',
  styleUrl: './create-request.component.scss',
  standalone: false
})
export class CreateRequestComponent implements OnInit, AfterViewInit {

  @ViewChild('dniInput', { static: false }) dniInput: ElementRef;
  @ViewChild('documentInput', { static: false }) documentInput: ElementRef;
  organizations !: TypeOrganzation[];
  entityDocumentTypes: EntityDocumentType[] = [];
  identityDocs: IdentityDocuments[] = [];
  cities: Cities[] = [];
  countries: Country[] = [];
  issuanceProvider: string | null = 'mail';
  customForm: FormGroup;
  loading: boolean = false;
  protected title: string = 'Datos para solicitud de certificado';
  protected validities = [
    { id: 1, name: '1 año' },
    { id: 2, name: '2 años' },
  ];
  files = [];
  formData: FormData;
  canEdit: boolean = false;
  buttonText: string = 'Crear solicitud';
  private lastLookupDni: string = '';

  // Límite total de archivos: 10MB
  private readonly MAX_TOTAL_SIZE = 10 * 1024 * 1024; // 10MB

  /**
   * Verifica si se puede guardar el formulario
   */
  get canSaveForm(): boolean {
    // Si está editando, siempre puede guardar
    if (this.canEdit) return true;

    // Si es viafirma, los archivos no son obligatorios en este componente
    if (this.isViafirma()) return true;

    // Determinar si es persona jurídica o natural
    const isPersonaJuridica = !this.isNaturelPerson();

    if (isPersonaJuridica) {
      // Persona Jurídica: REQUIERE 3 archivos (Cédula, RUT y Cámara)
      return this.files.length === 3;
    } else {
      // Persona Natural: REQUIERE 2 archivos (Cédula y RUT)
      return this.files.length === 2;
    }
  }

  /**
   * Obtiene el mensaje de validación según el tipo de persona
   */
  get documentValidationMessage(): string {
    const isPersonaJuridica = !this.isNaturelPerson();
    const requiredCount = isPersonaJuridica ? 3 : 2;
    const currentCount = this.files.length;

    if (this.isViafirma()) return 'No se requieren archivos para crear la solicitud.';

    if (currentCount === 0) {
      if (isPersonaJuridica) {
        return 'Debe subir 3 archivos: Cédula, RUT y Cámara de Comercio';
      } else {
        return 'Debe subir 2 archivos: Cédula y RUT';
      }
    }

    if (currentCount < requiredCount) {
      const missing = requiredCount - currentCount;
      return `Faltan ${missing} archivo(s). Total requerido: ${requiredCount}`;
    }

    if (currentCount > requiredCount) {
      const extra = currentCount - requiredCount;
      return `Tiene ${extra} archivo(s) de más. Total permitido: ${requiredCount}`;
    }

    return 'Documentos completos';
  }

  /**
   * Obtiene el texto de ayuda dinámico según el tipo de persona
   */
  get dynamicHelpText(): string {
    const isPersonaJuridica = !this.isNaturelPerson();

    if (isPersonaJuridica) {
      return 'Sube <strong>EXACTAMENTE 3 archivos</strong>:<br>' +
        '1. <strong>Cédula del Representante Legal</strong> (PDF, JPG o PNG)<br>' +
        '2. <strong>RUT Actualizado</strong> (PDF)<br>' +
        '3. <strong>Certificado de Cámara de Comercio</strong> (PDF)';
    } else {
      return 'Sube <strong>EXACTAMENTE 2 archivos</strong>:<br>' +
        '1. <strong>Cédula de Ciudadanía</strong> (PDF, JPG o PNG)<br>' +
        '2. <strong>RUT Actualizado</strong> (PDF)';
    }
  }

  /**
   * Obtiene el número máximo de archivos según el tipo de persona
   */
  get maxFilesAllowed(): number {
    const isPersonaJuridica = !this.isNaturelPerson();
    return isPersonaJuridica ? 3 : 2;
  }

  // Configuración ÚNICA para todos los archivos (se actualiza dinámicamente)
  get uploadConfig(): FileUploadConfig {
    const isPersonaJuridica = !this.isNaturelPerson();
    const maxFiles = isPersonaJuridica ? 3 : 2;
    const label = isPersonaJuridica ? 'Documentos Requeridos (3 archivos)' : 'Documentos Requeridos (2 archivos)';

    return {
      id: 'filesUpload',
      name: 'filesUpload',
      label: label,
      helpText: this.dynamicHelpText,
      acceptedFormats: ['pdf', 'jpg', 'jpeg', 'png'],
      maxTotalSize: this.MAX_TOTAL_SIZE,
      multiple: true,
      maxFiles: maxFiles,
      required: true,
      showPreview: true,
      enableDragDrop: true,
      icon: 'fas fa-upload',
      dropzoneText: `Arrastra hasta ${maxFiles} archivos aquí o haz clic para seleccionar`
    };
  }

  // inject services

  protected countrySer: CountriesService = inject(CountriesService);

  constructor(private fb: FormBuilder,
    private _http: HttpResponsesService,
    private _msg: MessagesService,
    private _router: Router,
    public company: CompanyService,
    private documentSer: DocumentsService,
    private _cities: CitiesService,
    private _activatedRoute: ActivatedRoute,
    private mask: LoadMaskService,
    protected _token: TokenService,
    private debug: DebugService,
  ) {

  }

  ngAfterViewInit(): void {
  }

  ngOnInit(): void {
    this.issuanceProvider = this._token.getToken()?.company?.issuance_provider || 'mail';

    this.documentSer.getIdentityDocuments({}).subscribe((resp) => {
      this.identityDocs = resp;
    });

    this.documentSer.getEntityDocumentTypes({}).subscribe((resp) => {
      this.entityDocumentTypes = resp;
    });

    this._cities.getData({}).subscribe((resp) => {
      this.cities = resp;
    });

    this.countrySer.getData().subscribe((resp) => {
      this.countries = resp;
    });


    this.documentSer.getTypeOrganization({}).subscribe((resp) => {
      this.organizations = resp;
    });
    this.onCreateForm();
    this.updateDynamicValidators();
    const id = this._activatedRoute.snapshot.paramMap.get('id');
    if (id) {
      this.getData(parseInt(id));
    }
  }


  get f() {
    return this.customForm.controls;
  }

  onCreateForm(): void {
    const ts = this;
    ts.customForm = ts.fb.group({
      company_name: ['', [Validators.required, Validators.minLength(2)]],
      legal_representative: ['', [Validators.required, Validators.minLength(2)]],
      legal_rep_first_name: [''],
      legal_rep_last_name: [''],
      legal_rep_email: ['', [Validators.pattern('^[a-z0-9._%+-ñ]+@[a-z0-9.-]+\.[a-z]{2,4}$')]],
      dni: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(12)]],
      document_number: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(12)]],
      identity_document_id: [1, [Validators.required]],
      type_organization_id: [1, Validators.required],
      entity_document_type_id: [1, Validators.required],
      mobile: [''],
      phone: [''],
      info: [''],
      address: ['', [Validators.required, Validators.minLength(10)]],
      city_id: [149, Validators.required],
      dv: [''],
      life: [1, Validators.required],
      country_id: [45, [Validators.required]],
    });
  }

  isInvalid(controlName: string): boolean {
    const ts = this;
    const frm = ts.customForm;
    return frm.get(controlName)?.invalid && frm.get(controlName)?.touched || false;
  }

  onValidateForm(form: FormGroup): void {
    Object.values(form.controls).forEach(ele => {
      ele.markAllAsTouched();
    });
  }

  getData(id: number): void {
    this.canEdit = true;
    this.buttonText = 'Actualizar solicitud';
    this._http.get(`/certificate-request/${id}`).subscribe((data: any) => {
      const resp = data.dataRecords.data[0] as any;
      this.customForm.patchValue(resp);
      this.customForm.get('dni')?.setValue(resp.dni);
      this.customForm.get('document_number')?.setValue(resp.document_number);
      this.customForm.get('company_name')?.setValue(resp.company_name);
      this.customForm.get('legal_representative')?.setValue(resp.legal_representative);
      this.customForm.get('legal_rep_first_name')?.setValue(resp.legal_rep_first_name);
      this.customForm.get('legal_rep_last_name')?.setValue(resp.legal_rep_last_name);
      this.customForm.get('legal_rep_email')?.setValue(resp.legal_rep_email);
      this.customForm.get('identity_document_id')?.setValue(resp.identity_document_id);
      this.customForm.get('type_organization_id')?.setValue(resp.type_organization_id);
      this.customForm.get('entity_document_type_id')?.setValue(resp.entity_document_type_id);
      this.customForm.get('mobile')?.setValue(resp.mobile);
      this.customForm.get('phone')?.setValue(resp.phone);
      this.customForm.get('info')?.setValue(resp.info);
      this.customForm.get('address')?.setValue(resp.address);
      this.customForm.get('city_id')?.setValue(resp.city_id);
      this.customForm.get('dv')?.setValue(resp.dv);
      this.customForm.get('life')?.setValue(resp.life);
      this.customForm.get('country_id')?.setValue(resp.country_id);
    });
  }

  onSave(): void {
    try {
      const ts = this;
      const frm = ts.customForm;

      if (ts.isViafirma() && ts.isNaturelPerson()) {
        const firstName = frm.get('legal_rep_first_name')?.value || '';
        const lastName = frm.get('legal_rep_last_name')?.value || '';
        frm.get('company_name')?.setValue(`${firstName} ${lastName}`.trim());
      }
      if (ts.isViafirma()) {
        const firstName = frm.get('legal_rep_first_name')?.value || '';
        const lastName = frm.get('legal_rep_last_name')?.value || '';
        frm.get('legal_representative')?.setValue(`${firstName} ${lastName}`.trim());
      }

      if (ts.isViafirma() && !ts.isNaturelPerson() && frm.get('entity_document_type_id')?.value == 0) {
        throw new Error('Por favor seleccione el tipo de documento constitutivo');
      } else {
        frm.get('entity_document_type_id')?.setValue(1);
      }

      ts.onValidateForm(frm);
      if (frm.invalid) {
        let invalidFields = [];
        for (const name in frm.controls) {
          if (frm.controls[name].invalid) {
            invalidFields.push(name);
          }
        }
        throw new Error(`Por favor llene la información correctamente. Campos con error: ${invalidFields.join(', ')}`);
      }

      // Validar cantidad de archivos requeridos según tipo de persona
      if (!ts.canEdit && !ts.isViafirma()) {
        const isPersonaJuridica = !ts.isNaturelPerson();
        const requiredFiles = isPersonaJuridica ? 3 : 2;

        if (ts.files.length < requiredFiles) {
          const tipo = isPersonaJuridica ? 'Persona Jurídica' : 'Persona Natural';
          throw new Error(`Debe cargar exactamente ${requiredFiles} archivos para ${tipo}.`);
        }

        if (ts.files.length > requiredFiles) {
          const tipo = isPersonaJuridica ? 'Persona Jurídica' : 'Persona Natural';
          throw new Error(`Ha excedido el límite. Solo se permiten ${requiredFiles} archivos para ${tipo}.`);
        }
      }

      let params = frm.getRawValue();
      params.dni = params.dni.replace(/[^a-zA-Z0-9]/g, '');
      params.document_number = params.document_number.replace(/[^a-zA-Z0-9]/g, '');
      ts.loading = true;

      if (!ts.canEdit && !ts.isViafirma()) {
        // Crear objeto con archivos en base64 en lugar de FormData
        params.attachments = ts.files.map((file: any) => ({
          name: file.data.name,
          type: file.data.type,
          size: file.data.size,
          base64: file.base64
        }));
      }
      this.mask.showBlockUI('Procesando solicitud...');
      if (ts.canEdit) {
        const data = ts.customForm.getRawValue();
        const id = ts._activatedRoute.snapshot.paramMap.get('id');
        this._http.put(`/certificate-request/${id}`, data)
          .subscribe({
            next: (resp) => {
              ts.finalResponse(resp);
            },
            error: () => {
              ts.onError();
            }
          });
      } else {
        const payload = ts.isViafirma() ? params : params;
        this._http.post('/certificate-request', payload)
          .subscribe({
            next: (resp) => {
              ts.finalResponse(resp);
            },
            error: (err) => {
              ts.onError();
              ts.handleHttpError(err);
            }
          });
      }

    } catch (e) {
      this.debug.error('CreateRequestComponent', 'Error al crear solicitud', e);
      this.loading = false;
      this.mask.hideBlockUI();
      this._msg.errorMessage('Error', e.message);
    }
  }

  protected onError() {
    this.mask.hideBlockUI();
    this.loading = false;
  }

  protected finalResponse(resp: any) {
    const ts = this;
    ts._msg.toastMessage('Éxito', resp.message);
    ts.mask.hideBlockUI();
    ts.loading = false;
    setTimeout(() => {
      ts._router.navigate(['/requests/list']);
    }, 2000);
  }


  protected isNaturelPerson(): boolean {
    if (!this.customForm) return false;
    return parseFloat(this.customForm.get('type_organization_id')?.value) === 2;
  }

  isViafirma(): boolean {
    return this.issuanceProvider === 'viafirma';
  }

  private updateDynamicValidators() {
    const frm = this.customForm;
    if (!frm) return;

    if (this.isViafirma()) {
      frm.get('legal_rep_first_name')?.setValidators([Validators.required, Validators.minLength(2)]);
      frm.get('legal_rep_last_name')?.setValidators([Validators.required, Validators.minLength(2)]);
      frm.get('legal_rep_email')?.setValidators([Validators.required, Validators.email]);
      frm.get('legal_representative')?.clearValidators();

      if (this.isNaturelPerson()) {
        frm.get('company_name')?.clearValidators();
      } else {
        frm.get('company_name')?.setValidators([Validators.required, Validators.minLength(5)]);
      }
    } else {
      frm.get('legal_rep_first_name')?.clearValidators();
      frm.get('legal_rep_last_name')?.clearValidators();
      frm.get('legal_representative')?.setValidators([Validators.required, Validators.minLength(10)]);
      frm.get('company_name')?.setValidators([Validators.required, Validators.minLength(5)]);
    }

    frm.get('legal_rep_first_name')?.updateValueAndValidity();
    frm.get('legal_rep_last_name')?.updateValueAndValidity();
    frm.get('legal_rep_email')?.updateValueAndValidity();
    frm.get('legal_representative')?.updateValueAndValidity();
    frm.get('company_name')?.updateValueAndValidity();
  }

  protected onChangeTypeOrganization($event: any) {
    this.updateDynamicValidators();
    const isPersonaJuridica = !this.isNaturelPerson();
    const maxFiles = isPersonaJuridica ? 3 : 2;

    // Si cambia de tipo y tiene más archivos de los permitidos, advertir al usuario
    if (this.files.length > maxFiles) {
      this._msg.toastMessage(
        'Atención',
        `Ha cambiado a ${isPersonaJuridica ? 'Persona Jurídica' : 'Persona Natural'}. ` +
        `Solo se permiten ${maxFiles} archivos. Por favor, ajuste los documentos.`,
        4
      );
    }
  }

  /**
   * Calcula el tamaño total de todos los archivos cargados
   */
  getTotalUploadedSize(): number {
    return this.files.reduce((sum, f) => sum + f.data.size, 0);
  }

  /**
   * Maneja la selección de archivos
   */
  onFileSelected(fileData: FileUploadData): void {
    // Verificar que no se excedan 3 archivos
    if (this.files.length >= 3) {
      this._msg.errorMessage('Límite de archivos alcanzado', 'Solo se permiten 3 archivos como máximo. Si necesitas reemplazar uno, elimínalo primero y luego sube el nuevo.');
      return;
    }

    // Verificar que no exista un archivo con el mismo nombre
    const index = this.files.findIndex((f: any) => f.data.name === fileData.data.name);
    if (index !== -1) {
      // Reemplazar archivo existente
      this._msg.toastMessage('Archivo reemplazado', `El archivo "${fileData.data.name}" fue reemplazado correctamente.`);
      this.files.splice(index, 1);
    }

    // Convertir archivo a base64 antes de agregarlo
    this.convertFileToBase64(fileData);
  }

  /**
   * Maneja la eliminación de archivos
   */
  onFileRemoved(fileName: string): void {
    const index = this.files.findIndex((f: any) => f.data.name === fileName);
    if (index !== -1) {
      this.files.splice(index, 1);
    }
  }

  /**
   * Maneja errores de validación
   */
  onFileValidationError(error: string): void {
    this.debug.warn('CreateRequestComponent', 'File validation error', error);
  }

  protected onLookupDni() {
    const dni = this.customForm.get('dni')?.value;
    if (!dni || this.canEdit || dni === this.lastLookupDni) return;

    this.lastLookupDni = dni;

    this._http.get(`/certificate-request/lookup/${dni}`).subscribe({
      next: (resp: any) => {
        if (resp.dataRecords.data) {
          this.customForm.patchValue(resp.dataRecords.data);
          this._msg.toastInfo(`Se encontraron datos asociados al N.I.T ${dni}. Los campos del formulario han sido completados automáticamente.`);
        } else {
          this.defaultFormValues();
        }
      },
      error: () => {
        // Ignorar el error 404 silenciosamente
      }
    });
  }

  protected onChangeDni($event: Event) {
    const ts = this;
    const input = $event.target as HTMLInputElement;
    const value = input.value.replace(/[^a-zA-Z0-9]/g, '');
    ts.customForm.get('dni')?.setValue(value);
    if (value.length > 5) {
      ts.dniInput.nativeElement.blur();
    }
  }

  protected onChangeDocument($event: Event) {
    const ts = this;
    const input = $event.target as HTMLInputElement;
    const value = input.value.replace(/[^a-zA-Z0-9]/g, '');
    ts.customForm.get('document_number')?.setValue(value);
    if (value.length > 5) {
      ts.documentInput.nativeElement.blur();
    }
  }

  /**
   * Maneja errores HTTP específicos al crear/editar solicitud.
   * HTTP 402 indica que el cupo se agotó: redirige al flujo de compra.
   */
  private handleHttpError(err: any): void {
    if (err instanceof HttpErrorResponse && err.status === 402) {
      this._msg.toastMessage(
        'Sin cupos disponibles',
        'No tiene certificados disponibles. Será redirigido al módulo de compra para adquirir un paquete.',
        3
      );
      setTimeout(() => {
        this._router.navigate(['/orders/purchase']);
      }, 2500);
    }
  }

  onCancel() {
    const ts = this;
    ts._msg.confirm('¿Está seguro de cancelar la solicitud?', 'Esta acción no se puede deshacer.')
      .then((result) => {
        if (result.isConfirmed) {
          ts._router.navigate(['/requests/list']);
        }
      });
  }

  /**
   * Convierte un archivo a base64 y lo agrega a la lista de archivos
   */
  private convertFileToBase64(fileData: FileUploadData): void {
    const reader = new FileReader();
    reader.onload = (e) => {
      // Crear un nuevo objeto con el contenido en base64
      const base64Data = e.target?.result as string;
      const fileWithBase64: any = {
        ...fileData,
        base64: base64Data
      };
      this.files.push(fileWithBase64);
    };
    reader.onerror = () => {
      this.debug.error('CreateRequestComponent', 'Error al convertir archivo a base64');
      this._msg.errorMessage('Error', 'No se pudo procesar el archivo. Por favor, intenta de nuevo.');
    };
    reader.readAsDataURL(fileData.data);
  }

  private defaultFormValues() {
    this.customForm.get('document_number')?.setValue('');
    this.customForm.get('company_name')?.setValue('');
    this.customForm.get('legal_representative')?.setValue('');
    this.customForm.get('legal_rep_first_name')?.setValue('');
    this.customForm.get('legal_rep_last_name')?.setValue('');
    this.customForm.get('legal_rep_email')?.setValue('');
    this.customForm.get('mobile')?.setValue('');
    this.customForm.get('phone')?.setValue('');
    this.customForm.get('info')?.setValue('');
    this.customForm.get('address')?.setValue('');
    this.customForm.get('city_id')?.setValue(149);
    this.customForm.get('dv')?.setValue('');
    this.customForm.get('life')?.setValue(1);
    this.customForm.get('country_id')?.setValue(45);
  }

  onImageError(event: any): void {
    if (event.target && event.target instanceof HTMLImageElement) {
      event.target.src = 'assets/flags/empty-flag.png';
    }
  }

  getCountryFlagPath(countryImage: string): string {
    if (!countryImage || countryImage.trim() === '') {
      return 'assets/flags/empty-flag.png';
    }

    let imageName = countryImage.trim();

    if (!imageName.match(/\.(png|jpg|jpeg|gif)$/i)) {
      imageName = imageName.toLowerCase() + '.png';
    }

    return `assets/flags/${imageName}`;
  }
}
