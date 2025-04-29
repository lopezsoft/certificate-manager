


export enum OrderStatus {
	Pending = 'Pending',
	Confirmed = 'Confirmed',
	Preparing = 'Preparing',
	Ready = 'Ready',
	InTransit = 'InTransit',
	Delivered = 'Delivered',
	Completed = 'Completed',
	Cancelled = 'Cancelled',
	Partially = 'Partially',
}

export enum OrderType {
	DineIn = 'DineIn',
	Delivery = 'Delivery',
	Takeaway = 'Takeaway',
}

export function getOrderStatus(status: OrderStatus): string {
	switch (status) {
		case OrderStatus.Pending:
			return 'Pendiente';
		case OrderStatus.Confirmed:
			return 'Confirmado';
		case OrderStatus.Preparing:
			return 'Preparando';
		case OrderStatus.Ready:
			return 'Listo';
		case OrderStatus.InTransit:
			return 'En camino';
		case OrderStatus.Delivered:
			return 'Entregado';
		case OrderStatus.Completed:
			return 'Completado';
		case OrderStatus.Cancelled:
			return 'Cancelado';
		case OrderStatus.Partially:
			return 'Parcialmente';
		default:
			return 'Desconocido';
	}
}

export function getOrderType(type: OrderType): string {
	switch (type) {
		case OrderType.DineIn:
			return 'Comer en el local';
		case OrderType.Delivery:
			return 'Entrega a domicilio';
		case OrderType.Takeaway:
			return 'Para llevar';
		default:
			return 'Desconocido';
	}
}