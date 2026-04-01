import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

dayjs.extend(utc);
dayjs.extend(timezone);

const BOGOTA_TZ = 'America/Bogota';

/**
 * Formatea una fecha al formato Carbon de Laravel: "d-m-Y h:i:s a"
 * Ejemplo: "26-03-2026 10:00:00 am"
 */
export function formatLaravelDate(date: Date | string | null | undefined): string | null {
  if (!date) return null;
  const d = dayjs(date);
  if (!d.isValid()) return null;
  return d.tz(BOGOTA_TZ).format('DD-MM-YYYY hh:mm:ss a');
}

/**
 * Formatea solo la fecha: "d-m-Y"
 * Ejemplo: "26-03-2026"
 */
export function formatLaravelDateOnly(date: Date | string | null | undefined): string | null {
  if (!date) return null;
  const d = dayjs(date);
  if (!d.isValid()) return null;
  return d.tz(BOGOTA_TZ).format('DD-MM-YYYY');
}
