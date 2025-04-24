import {ProductVariationTerms} from '../models/products-model';

export class ProductVariationManager {
	public static extractVariationsValue(array: ProductVariationTerms[]): string {
		const values = array.map((row: ProductVariationTerms) => {
			return `${row.attribute_name}: ${row.term_name}`;
		});
		return values.join(' / ');
	}

	public static getRGBAColor(hex: string, alpha: number): string {
		const r: number = parseInt(hex.slice(1, 3), 16);
		const g: number = parseInt(hex.slice(3, 5), 16);
		const b: number = parseInt(hex.slice(5, 7), 16);
		return `rgba(${r}, ${g}, ${b}, ${alpha})`;
	}
}
