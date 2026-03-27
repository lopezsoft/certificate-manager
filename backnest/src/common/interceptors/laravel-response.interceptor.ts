import {
  CallHandler,
  ExecutionContext,
  Injectable,
  NestInterceptor,
} from '@nestjs/common';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

/**
 * Envuelve todas las respuestas exitosas en el formato Laravel:
 * { success: true, ...data }
 *
 * Si el controlador ya devuelve { success, dataRecords } se pasa sin envolver.
 * Si devuelve { data, ... } con estructura de paginación se respeta como está.
 */
@Injectable()
export class LaravelResponseInterceptor implements NestInterceptor {
  intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
    return next.handle().pipe(
      map((value) => {
        // Null / undefined → vacío
        if (value === null || value === undefined) {
          return { success: true };
        }

        // Ya tiene la forma correcta
        if (typeof value === 'object' && 'success' in value) {
          return value;
        }

        // Spread del objeto devuelto por el controlador
        if (typeof value === 'object') {
          return { success: true, ...value };
        }

        // Primitivo (string, number)
        return { success: true, data: value };
      }),
    );
  }
}
