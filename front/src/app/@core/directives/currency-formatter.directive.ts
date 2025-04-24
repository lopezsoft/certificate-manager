import {AfterViewInit, Directive, ElementRef, HostListener, Input, OnInit} from '@angular/core';

@Directive({
  selector: '[appCurrencyFormatter]'
})
export class CurrencyFormatterDirective implements OnInit, AfterViewInit {
  @Input() currencyCode = 'COP';
  @Input() locales      = 'es-CO';
  constructor(private el: ElementRef) { }
  ngOnInit() {
    // console.log('Currency', this.el.nativeElement.value);
  }
  ngAfterViewInit(): void {
    this.formatValue(this.el.nativeElement.value);
  }
  @HostListener('keydown', ['$event'])
  onKeyDown(event: KeyboardEvent) {
    const pattern = /[0-9\.\,]/;
    const allowedKeys = ['Enter', 'Delete', 'Backspace', 'ArrowLeft', 'ArrowRight', 'Tab'];
    if (!pattern.test(event.key) && !allowedKeys.includes(event.key)) {
      // Carácter no permitido
      event.preventDefault();
    }
  }
  @HostListener('blur', ['$event.target.value'])
  onBlur(value: string) {
    this.formatValue(value);
  }
  @HostListener('focus', ['$event'])
  onFocus(event: Event): void {
    // Cast the event target to HTMLInputElement
    const inputElement = event.target as HTMLInputElement;
    inputElement.select();
    inputElement.focus();
  }
  private sanitizeValue(value: string): string {
    const dots    = value.split('.').length - 1;
    const commas  = value.split(',').length - 1;

    let decimalSeparator = '.';
    let thousandSeparator = ',';

    if (commas === 1 && dots !== 1) {
      decimalSeparator  = ',';
      thousandSeparator = '.';
    }

    // Remove thousand separators
    const withoutThousandSeparators = value.replace(new RegExp(`\\${thousandSeparator}`, 'g'), '');

    // Replace decimal separator with dot
    return withoutThousandSeparators.replace(decimalSeparator, '.');
  }

  private formatValue(value: string): void {
    const sanitizedValue  = this.sanitizeValue(value);
    const numericValue    = parseFloat(sanitizedValue);

    if (!isNaN(numericValue)) {
      // Determine the number of decimal places
      const decimalPlaces = (sanitizedValue.split('.')[1] || []).length;
      this.el.nativeElement.value = new Intl.NumberFormat(this.locales, {
        style: 'currency',
        currency: this.currencyCode,
        minimumFractionDigits: decimalPlaces,
        maximumFractionDigits: decimalPlaces,
        currencyDisplay: 'narrowSymbol'
      }).format(numericValue);
    }
  }



}
