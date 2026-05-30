/**
 * Estados de una orden de compra de certificados.
 *
 * @enum {string}
 */
export enum OrderStatusEnum {
  PENDING   = 'PENDING',
  PAID      = 'PAID',
  CANCELLED = 'CANCELLED',
  EXPIRED   = 'EXPIRED',
  FAILED    = 'FAILED',
}

export const OrderStatusEnumArray = [
  OrderStatusEnum.PENDING,
  OrderStatusEnum.PAID,
  OrderStatusEnum.CANCELLED,
  OrderStatusEnum.EXPIRED,
  OrderStatusEnum.FAILED,
];

export const OrderStatusDescription: Record<string, string> = {
  PENDING:   'Pendiente de pago',
  PAID:      'Pagada',
  CANCELLED: 'Cancelada',
  EXPIRED:   'Expirada',
  FAILED:    'Fallida',
};
