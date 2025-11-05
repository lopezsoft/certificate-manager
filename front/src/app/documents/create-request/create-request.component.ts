import {AfterViewInit, Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {Cities, IdentityDocuments, TypeOrganzation} from "../../models/general-model";
import {FormBuilder, FormGroup, Validators} from "@angular/forms";
import {HttpResponsesService, MessagesService} from "../../utils";
import {ActivatedRoute, Router} from "@angular/router";
import {CompanyService} from "../../services/companies";
import {CitiesService, DocumentsService} from "../../services/general";
import {LoadMaskService} from "../../services/load-mask.service";
import TokenService from "../../utils/token.service";
import {FileUploadConfig} from "../../shared/components/file-upload/file-upload-config.interface";
import {FileUploadData} from "../../shared/components/file-upload/file-upload.component";

@Component({
  selector: 'app-create-request',
  templateUrl: './create-request.component.html',
  styleUrl: './create-request.component.scss'
})
export class CreateRequestComponent implements OnInit, AfterViewInit {

  @ViewChild('dniInput', { static: false}) dniInput: ElementRef;
  @ViewChild('documentInput', { static: false}) documentInput: ElementRef;
  organizations !: TypeOrganzation[];
  identityDocs: IdentityDocuments[] = [];
  cities: Cities[] = [];
  customForm  : FormGroup;
  loading     : boolean = false;
  protected title: string = 'Datos para solicitud de certificado';
  protected validities = [
    {id: 1, name: '1 año'},
    {id: 2, name: '2 años'},
  ];
  files = [];
  formData: FormData;
  canEdit: boolean = false;
  hasCc: boolean = false;
  hasRut: boolean = false;
  hastCamera: boolean = false;
  buttonText: string = 'Crear solicitud';
  
  // Límite total de archivos: 10MB
  private readonly MAX_TOTAL_SIZE = 10 * 1024 * 1024; // 10MB

  /**
   * Verifica si se puede guardar el formulario
   */
  get canSaveForm(): boolean {
    // Si está editando, siempre puede guardar
    if (this.canEdit) return true;
    // Si está creando, necesita al menos 2 archivos
    return this.files.length >= 2;
  }

  // Configuración ÚNICA para todos los archivos
  uploadConfig: FileUploadConfig = {
    id: 'filesUpload',
    name: 'filesUpload',
    label: 'Documentos Requeridos',
    helpText: 'Sube los siguientes archivos:<br>1. <strong>Certificado de Cámara de Comercio</strong> (PDF) - No mayor a 30 días<br>2. <strong>RUT Actualizado</strong> (PDF)<br>3. <strong>Cédula del Representante Legal</strong> (PDF, JPG o PNG)',
    acceptedFormats: ['pdf', 'jpg', 'jpeg', 'png'],
    maxTotalSize: this.MAX_TOTAL_SIZE,
    multiple: true,
    maxFiles: 3,
    required: true,
    showPreview: true,
    enableDragDrop: true,
    icon: 'fas fa-upload',
    dropzoneText: 'Arrastra hasta 3 archivos aquí o haz clic para seleccionar'
  };
  
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
  ) {

  }

  ngAfterViewInit(): void {
    const id = this._activatedRoute.snapshot.paramMap.get('id');
    if (id) {
      this.getData(parseInt(id));
    }
  }

  ngOnInit(): void {
    this.documentSer.getIdentityDocuments({}).subscribe((resp) => {
      this.identityDocs  = resp;
    });

    this._cities.getData({}).subscribe((resp) => {
      this.cities  = resp;
    });
    this.documentSer.getTypeOrganization({}).subscribe((resp) => {
      this.organizations  = resp;
    });
    this.onCreateForm();
  }


  get f() {
    return this.customForm.controls;
  }

  onCreateForm() : void {
    const ts  = this;
    ts.customForm = ts.fb.group({
      company_name          : ['',[Validators.required, Validators.minLength(5)]],
      legal_representative  : ['', [Validators.required, Validators.minLength(10)]],
      dni                   : ['',[Validators.required, Validators.minLength(5), Validators.maxLength(12)]],
      document_number       : ['',[Validators.required, Validators.minLength(5), Validators.maxLength(12)]],
      identity_document_id  : [1, [Validators.required]],
      type_organization_id  : [1,Validators.required],
      mobile                : [''],
      phone                 : [''],
      info                  : [''],
      address               : ['',[Validators.required, Validators.minLength(10)]],
      city_id               : [149,Validators.required],
      dv                    : [''],
      life                  : [1, Validators.required],
    });
  }

  isInvalid(controlName: string) : boolean {
    const ts  = this;
    const frm = ts.customForm;
    return frm.get(controlName)?.invalid && frm.get(controlName)?.touched || false;
  }

  onValidateForm(form: FormGroup): void {
    Object.values(form.controls).forEach(ele => {
      ele.markAllAsTouched();
    });
  }

  getData(id: number) : void {
    this.canEdit = true;
    this.buttonText = 'Actualizar solicitud';
    this._http.get(`/certificate-request/${id}`).subscribe((data: any) => {
      const resp = data.dataRecords.data[0] as any;
      this.customForm.patchValue(resp);
      this.customForm.get('dni')?.setValue(resp.dni);
      this.customForm.get('document_number')?.setValue(resp.document_number);
      this.customForm.get('company_name')?.setValue(resp.company_name);
      this.customForm.get('legal_representative')?.setValue(resp.legal_representative);
      this.customForm.get('identity_document_id')?.setValue(resp.identity_document_id);
      this.customForm.get('type_organization_id')?.setValue(resp.type_organization_id);
      this.customForm.get('mobile')?.setValue(resp.mobile);
      this.customForm.get('phone')?.setValue(resp.phone);
      this.customForm.get('info')?.setValue(resp.info);
      this.customForm.get('address')?.setValue(resp.address);
      this.customForm.get('city_id')?.setValue(resp.city_id);
      this.customForm.get('dv')?.setValue(resp.dv);
      this.customForm.get('life')?.setValue(resp.life);
    });
  }

  onSave() : void {
    try {
      const ts    = this;
      const frm   = ts.customForm;
      ts.onValidateForm(frm);
      if(frm.invalid) {
        throw new Error('Por favor llene la información de cada campo');
      }
      
      // Validar archivos mínimos requeridos
      if (!ts.canEdit) {
        if (ts.files.length < 2) {
          throw new Error('Debe cargar al menos 2 archivos para poder guardar la solicitud.');
        }
        if (!ts.hasRut) {
          throw new Error('Por favor suba el RUT.');
        }
        if (!ts.hasCc) {
          throw new Error('Por favor suba la cédula del representante legal.');
        }
        if (!ts.hastCamera && !ts.isNaturelPerson()) {
          throw new Error('Por favor suba el certificado de Cámara de Comercio.');
        }
      }
      
      let params            =  frm.getRawValue();
      params.dni            = params.dni.replace(/[^0-9]/g, '');
      params.document_number= params.document_number.replace(/[^0-9]/g, '');
      ts.loading            = true;
      
      if (!ts.canEdit) {
        ts.formData = new FormData();
        // Append all files to formData
        ts.files.forEach((file: any, index) => {
          ts.formData.append('file' + index, file.data);
        });
        // Append all form values to formData
        for (const key in params) {
          if (params.hasOwnProperty(key)) {
            ts.formData.append(key, params[key]);
          }
        }
      }
      this.mask.showBlockUI('Procesando solicitud...');
      if (ts.canEdit) {
        const data  = ts.customForm.getRawValue();
        const id    = ts._activatedRoute.snapshot.paramMap.get('id');
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
      this._http.post('/certificate-request', ts.formData)
          .subscribe({
              next: (resp) => {
                ts.finalResponse(resp);
              },
              error: () => {
                ts.onError()
              }
          });
      }

    }catch (e) {
      console.error(e);
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
    const frm = this.customForm;
    const res =  parseFloat(frm.get('type_organization_id')?.value) === 2;
    if (res) {
      frm.get('legal_representative')?.setValue(frm.get('company_name')?.value);
    }
    return res
  }
  protected onChangeTypeOrganization($event: any) {
    console.log($event);
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
    
    this.files.push(fileData);
    this.updateFileFlags();
  }

  /**
   * Maneja la eliminación de archivos
   */
  onFileRemoved(fileName: string): void {
    const index = this.files.findIndex((f: any) => f.data.name === fileName);
    if (index !== -1) {
      this.files.splice(index, 1);
      this.updateFileFlags();
    }
  }

  /**
   * Actualiza los flags de archivos según lo que se ha subido
   */
  private updateFileFlags(): void {
    this.hastCamera = this.files.some((f: any) => 
      f.data.name.toLowerCase().includes('camara') || 
      f.data.name.toLowerCase().includes('comercio')
    );
    
    this.hasRut = this.files.some((f: any) => 
      f.data.name.toLowerCase().includes('rut')
    );
    
    this.hasCc = this.files.some((f: any) => 
      f.data.name.toLowerCase().includes('cedula') || 
      f.data.name.toLowerCase().includes('cédula') ||
      f.data.name.toLowerCase().includes('cc')
    );
  }

  /**
   * Maneja errores de validación
   */
  onFileValidationError(error: string): void {
    console.warn('File validation error:', error);
  }

  protected onChangeDni($event: Event) {
    const ts = this;
    const input = $event.target as HTMLInputElement;
    const value = input.value.replace(/[^0-9]/g, '');
    ts.customForm.get('dni')?.setValue(value);
    if (value.length > 5) {
      ts.dniInput.nativeElement.blur();
    }
  }

  protected onChangeDocument($event: Event) {
    const ts = this;
    const input = $event.target as HTMLInputElement;
    const value = input.value.replace(/[^0-9]/g, '');
    ts.customForm.get('document_number')?.setValue(value);
    if (value.length > 5) {
      ts.documentInput.nativeElement.blur();
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
}
