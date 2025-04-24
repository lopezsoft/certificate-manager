import { FormGroup } from "@angular/forms";


export default class DataValidator {
  static isInvalidFormField(controlName: string, form: FormGroup) : boolean {
    if(!form) {
      throw new Error("Propiedad form no definida");
    }
    const frm = form;
    return frm.get(controlName)?.invalid && frm.get(controlName)?.touched || false;
  }

  static isInvalidNumber (controlName: string, form: FormGroup): boolean {
		const frm   = form;
    if(!frm) {
      throw new Error("Propiedad form no definida");
    }
    return (frm.get(controlName)?.value <= 0) ? true : false;
  }

  static onValidateForm(form: FormGroup): void {
    if(!form) {
      throw new Error("Propiedad form no definida");
    }
    Object.values(form.controls).forEach(ele => {
      ele.markAllAsTouched();
    });
  }
}
