import {PaymentSale} from '../models/sales-model';
export class PaymentMethodManager {
	public static extractPaymentMethodValue(paymentMethod: PaymentSale[]): string {
		const values = paymentMethod.map((row: PaymentSale) => {
			return `${row.meansPayment.payment_method}`;
		});
		return values.join(';');
	}
	public static extractPaymentExpirationDate(paymentMethod: PaymentSale[]): string {
		const values = paymentMethod.map((row: PaymentSale) => {
			return `${row.expiration_date}`;
		});
		return values.join(';');
	}
}
