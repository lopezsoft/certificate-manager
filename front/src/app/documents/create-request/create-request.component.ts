import {AfterViewInit, Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {Cities, IdentityDocuments, TypeOrganzation} from "../../models/general-model";
import {FormBuilder, FormGroup, Validators} from "@angular/forms";
import {HttpResponsesService, MessagesService} from "../../utils";
import {ActivatedRoute, Router} from "@angular/router";
import {CompanyService} from "../../services/companies";
import {CitiesService, DocumentsService} from "../../services/general";
import {LoadMaskService} from "../../services/load-mask.service";

@Component({
  selector: 'app-create-request',
  templateUrl: './create-request.component.html',
  styleUrl: './create-request.component.scss'
})
export class CreateRequestComponent implements OnInit, AfterViewInit {

  @ViewChild('fileUpload', { static: false}) fileUpload: ElementRef;
  @ViewChild('fileUploadRut', { static: false}) fileUploadRut: ElementRef;
  @ViewChild('fileUploadCc', { static: false}) fileUploadCc: ElementRef;
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
  buttonText: string = 'Crear solicitud';
  constructor(private fb: FormBuilder,
              private _http: HttpResponsesService,
              private _msg: MessagesService,
              private _router: Router,
              public company: CompanyService,
              private documentSer: DocumentsService,
              private _cities: CitiesService,
              private _activatedRoute: ActivatedRoute,
              private mask: LoadMaskService,
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
    const ts    = this;
    const frm   = ts.customForm;
    ts.onValidateForm(frm);
    if(frm.invalid) {
      ts._msg.errorMessage('Error','Por favor llene la información de cada campo.');
      return;
    }
    if (ts.files.length < 2 && !ts.canEdit ) {
      ts._msg.errorMessage('Error','Por favor suba los documentos requeridos.');
      return;
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

  onUploadPDF() {
    const fileUpload = this.fileUpload.nativeElement;
    const file = fileUpload.files[0];
    // Check file size and type 1000kb = 75000
    if (file.size > 1000000) { // 1000kb
      this.fileUpload.nativeElement.value = '';
      const size = (file.size / 1024).toFixed(2); // Convert to KB
      this._msg.errorMessage('',`El archivo no debe ser mayor a 1000kb. Tamaño del archivo ${size}kb.`);
    } else if (file.type !== 'application/pdf') {
      this.fileUpload.nativeElement.value = '';
      this._msg.errorMessage('', 'Formato de archivo incorrecto');
    } else {
      // Check if exists file in array and remove it
      const index = this.files.findIndex((f: any) => f.data.name === file.name);
      if (index !== -1) {
        this.files.splice(index, 1);
      }
      this.files.push({ data: file, inProgress: false, progress: 0});
    }
  }

  onUploadRUT() {
    const fileUpload = this.fileUploadRut.nativeElement;
    const file = fileUpload.files[0];
    if (file.size > 1000000) { // 1000kb
      this.fileUploadRut.nativeElement.value = '';
      const size = (file.size / 1024).toFixed(2); // Convert to KB
      this._msg.errorMessage('',`El archivo no debe ser mayor a 1000kb. Tamaño del archivo ${size}kb.`);
    } else if (file.type !== 'application/pdf') {
      this.fileUploadRut.nativeElement.value = '';
      this._msg.errorMessage('', 'Formato de archivo incorrecto');
    } else {
      // Check if exists file in array and remove it
      const index = this.files.findIndex((f: any) => f.data.name === file.name);
      if (index !== -1) {
        this.files.splice(index, 1);
      }
      this.files.push({ data: file, inProgress: false, progress: 0});
    }
  }

  onUploadCC() {
    const fileUpload = this.fileUploadCc.nativeElement;
    const file = fileUpload.files[0];
    // Check file size and type 1000kb = 1000000
    if (file.size > 1000000) { // 1000kb
      this.fileUploadCc.nativeElement.value = '';
      const size = (file.size / 1024).toFixed(2); // Convert to KB
      this._msg.errorMessage('',`El archivo no debe ser mayor a 1000kb. Tamaño del archivo ${size}kb.`);
    } else {
      // Check if exists file in array and remove it
      const index = this.files.findIndex((f: any) => f.data.name === file.name);
      if (index !== -1) {
        this.files.splice(index, 1);
      }
      this.files.push({ data: file, inProgress: false, progress: 0});
    }
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
