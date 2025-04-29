// src/app/helpers/conversion.helper.ts

/**
 * Convierte un string a un número de manera segura.
 * @param value - El string que deseas convertir a número.
 * @returns El número convertido o NaN si la conversión falla.
 */
export function stringToNumber(value: string): number {
	const number = parseFloat(value);
	return isNaN(number) ? 0 : number; // Si la conversión falla, retorna 0.
}
/**
 * Consiste en convertir un valor numérico a un número.
 * @param value - El valor numérico que deseas convertir a número.
 * @returns El número convertido.
 */
export function numberValue(value: number): number {
	return parseFloat(value.toString());
}

/**
 * Obtiene el valor de un evento.
 * @param event
 * @returns El valor del evento.
 */
export function getTargetValue(event: any): number {
	let value = event.target.value;
	value     = value.replace(/,/g, '');
	if (value === '') {
		value = '0';
	}
	return parseFloat(value);
}

/**
 * Convierte el tamaño de un archivo de bytes a megabytes (MB).
 * @param sizeInBytesString El tamaño del archivo en bytes.
 * @returns El tamaño del archivo en megabytes con dos decimales de precisión.
 */
export  function convertBytesToMB(sizeInBytesString: string): string {
	const sizeInBytes = parseInt(sizeInBytesString, 10);
	if (isNaN(sizeInBytes)) {
		return 'Tamaño inválido';
	}
	const sizeInMB = sizeInBytes / (1024 * 1024);
	return sizeInMB.toFixed(2) + ' MB';
}


export function getRandomColor(): string {
	const letters = '0123456789ABCDEF';
	let color = '#';
	for (let i = 0; i < 6; i++) {
		color += letters[Math.floor(Math.random() * 16)];
	}
	return color;
}

export function getFirstLetter(name: string): string {
	return name.charAt(0).toLowerCase();
}


export function getColorClass(index: number): string {
	return `color-index-${(index % 7) + 1}`; // Alterna entre 7 colores
}

export function extractImageName(imagePath: string): string {
	const parts = imagePath.split('/');
	console.log(parts[parts.length - 1]);
	return parts[parts.length - 1];
}