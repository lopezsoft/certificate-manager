import {
  ArgumentsHost,
  Catch,
  ExceptionFilter,
  HttpException,
  HttpStatus,
} from '@nestjs/common';
import { FastifyReply } from 'fastify';
import { QueryFailedError } from 'typeorm';

interface ErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

@Catch()
export class LaravelExceptionFilter implements ExceptionFilter {
  catch(exception: unknown, host: ArgumentsHost): void {
    const ctx = host.switchToHttp();
    const reply = ctx.getResponse<FastifyReply>();

    if (exception instanceof HttpException) {
      const status = exception.getStatus();
      const exceptionResponse = exception.getResponse();

      // ValidationPipe lanza 422 con { message: string[] | string, error: string }
      if (status === HttpStatus.UNPROCESSABLE_ENTITY) {
        const rawErrors =
          typeof exceptionResponse === 'object' &&
            'message' in (exceptionResponse as object)
            ? (exceptionResponse as { message: string | string[] }).message
            : [];

        const errors = this.buildValidationErrors(rawErrors);

        const body: ErrorResponse = {
          success: false,
          message: 'Los datos enviados no son válidos.',
          errors,
        };
        reply.code(422).send(body);
        return;
      }

      const message =
        typeof exceptionResponse === 'string'
          ? exceptionResponse
          : (exceptionResponse as { message?: string }).message ??
          exception.message;

      reply.code(status).send({ success: false, message });
      return;
    }

    // Error de base de datos (unique constraint, etc.)
    if (exception instanceof QueryFailedError) {
      reply.code(HttpStatus.UNPROCESSABLE_ENTITY).send({
        success: false,
        message: 'Error en la base de datos.',
        errors: {},
      });
      return;
    }

    // Error genérico / inesperado
    const message =
      exception instanceof Error ? exception.message : 'Error interno del servidor.';

    reply.code(HttpStatus.INTERNAL_SERVER_ERROR).send({
      success: false,
      message,
    });
  }

  private buildValidationErrors(
    raw: string | string[],
  ): Record<string, string[]> {
    if (!Array.isArray(raw)) {
      return { general: [raw] };
    }
    // NestJS ValidationPipe genera mensajes como "field must be a string"
    const errors: Record<string, string[]> = {};
    for (const msg of raw) {
      // Intentar extraer campo del prefijo "field message"
      const parts = msg.split(' ');
      const field = parts.length > 1 ? parts[0] : 'general';
      if (!errors[field]) errors[field] = [];
      errors[field].push(msg);
    }
    return errors;
  }
}
