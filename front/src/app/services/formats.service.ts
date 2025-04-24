import { Injectable } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class FormatsService {
  constructor() { }
  public getCurrencyFormat(format: string, currency: string, value: number): string {
    if (format && currency) {
      const options = { style: 'currency', currency: currency, currencyDisplay: 'narrowSymbol' };
      const numberFormat = new Intl.NumberFormat(format, options);
      return numberFormat.format(value);
    }
    return value.toString();
  }
  /**
   * Sanitize the value to be parsed as float
   * */
  public getSanitizeValue(value: string): string {
    const dots = value.split('.').length - 1;
    const commas = value.split(',').length - 1;

    let decimalSeparator = '.';
    let thousandSeparator = ',';

    if (commas === 1 && dots !== 1) {
      decimalSeparator = ',';
      thousandSeparator = '.';
    }

    // Remove thousand separators
    const withoutThousandSeparators = value.replace(new RegExp(`\\${thousandSeparator}`, 'g'), '');

    // Replace decimal separator with dot
    const withDotAsDecimalSeparator = withoutThousandSeparators.replace(decimalSeparator, '.');

    // Convert to float to remove trailing zeros after decimal
    const floatValue = parseFloat(withDotAsDecimalSeparator);

    // Check if the number is an integer
    if (Number.isInteger(floatValue)) {
      // Return as integer string
      return floatValue.toString();
    } else {
      // Convert back to string and replace dot with original decimal separator
      return floatValue.toString().replace('.', decimalSeparator);
    }
  }

}
